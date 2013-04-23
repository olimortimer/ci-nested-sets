<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * File: libraries/Nested_set.php
 *
 * An implementation of Joe Celko's Nested Sets as a Code Igniter model.
 *
 * Updated by olimortimer to fix and improve
 * https://github.com/olimortimer/ci-nested-sets
 *
 * Updated by intel352 for unlimited root trees, also added ability to define
 * primary key column and parent column. Use of parent column can help lighten
 * the load of having a strict nested set model. It takes more of a hybrid approach.
 *
 * @package	 Nested_sets
 * @author	  Thunder <ravenvelvet@gmail.com>, intel352 <jon@phpsitesolutions.com>
 * @copyright   Copyright (c) 2007 Thunder; Copyright (c) 2008 intel352
 */


/**
 * @package Nested_set
 * @author  Thunder <ravenvelvet@gmail.com>, intel352 <jon@phpsitesolutions.com>, olimortimer <oli@olimortimer.com>
 * @version 1.2.1
 * @copyright Copyright (c) 2007 Thunder; Copyright (c) 2008 intel352
 * @todo	Keep semi-persistent model of data retrieved, to reduce query access of the same data.
 * 			Need to convert this to a generic TREE class, supporting nested and adjacent, a la cakephp
 */
class Nested_set {

	private $table_name;
	private $left_column_name;
	private $right_column_name;
	private $primary_key_column_name;
	private $parent_column_name;
	private $text_column_name;
	private $db;

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	public function __construct()	{
		$CI =& get_instance(); // to access CI resources, use $CI instead of $this
		$this->db =& $CI->db;
	}

	// -------------------------------------------------------------------------
	//  OBJECT INITIALISATION METHODS
	//
	//  For setting instance properties
	//
	// -------------------------------------------------------------------------

	/**
	 * On initialising the instance, this method should be called to set the
	 * database table name that we're dealing and also to identify the names
	 * of the left and right value columns used to form the tree structure.
	 * Typically, this would be done automatically by the model class that
	 * extends this "base" class (eg. a Categories class would set the table_name
	 * to "categories", a Site_structure class would set the table_name to
	 * "pages" etc)
	 *
	 * @param string $table_name The name of the db table to use
	 * @param string $left_column_name The name of the field representing the left identifier
	 * @param string $right_column_name The name of the field representing the right identifier
	 * @param string $primary_key_column_name The name of the primary identifier field
	 * @param string $parent_column_name The name of the parent column field
	 */
	public function setControlParams($table_name, $left_column_name = 'lft', $right_column_name = 'rgt', $primary_key_column_name = 'id', $parent_column_name = 'parent_id', $text_column_name = 'name') {
		$this->table_name = $table_name;
		$this->left_column_name = $left_column_name;
		$this->right_column_name = $right_column_name;
		$this->primary_key_column_name = $primary_key_column_name;
		$this->parent_column_name = $parent_column_name;
		$this->text_column_name = $text_column_name;
	}

	/**
	* Used to identify the primary key of the table in use. Commonly, this will
	* be an auto_incrementing ID column (eg CategoryId)
	*
	* @param string $primary_key_name
	*/
	public function setPrimaryKeyColumn($primary_key_name) {
		$this->primary_key_column_name = $primary_key_name;
	}


	// -------------------------------------------------------------------------
	//  NODE MANIPULATION FUNCTIONS
	//
	//  Methods to add/remove nodes in your tree
	//
	// -------------------------------------------------------------------------


	/**
	 * Adds the first entry to the table
	 * @param	 $extrafields  An array of field->value pairs for the database record
	 * @return	$node an array of left and right values
	 * @deprecated
	 */
	public function initialiseRoot($extrafields = array()) {
		return $this->insertNewTree($extrafields);
	}

	/**
	 * Adds the first entry to the table
	 * @param	 $extrafields  An array of field->value pairs for the database record
	 * @return	$node an array of left and right values
	 */
	public function insertNewTree($extrafields = array()) {

		$this->db->select_max($this->right_column_name, 'lft');
		$query = $this->db->get($this->table_name);
		$result = $query->row_array();

		$node = array(
		$this->parent_column_name => 0,
		$this->left_column_name  => $result['lft'] + 1,
		$this->right_column_name => $result['lft'] + 2,
		);

		$this->_setNewNode($node, $extrafields);

		return $this->getNodeWhereLeft($node[$this->left_column_name]);
	}

	/**
	 * inserts a new node as the first child of the supplied parent node
	 * @param array $parentNode The node array of the parent to use
	 * @param array $extrafields An associative array of fieldname=>value for the other fields in the recordset
	 * @return array $childNode An associative array representing the new node
	 */
	public function insertNewChild($parentNode, $extrafields = array()) {
		$childNode[$this->parent_column_name]	=	$parentNode[$this->primary_key_column_name];
		$childNode[$this->left_column_name]		=	$parentNode[$this->left_column_name]+1;
		$childNode[$this->right_column_name]	=	$parentNode[$this->left_column_name]+2;

		$this->_modifyNode($childNode[$this->left_column_name], 2);
		$this->_setNewNode($childNode, $extrafields);

		return $this->getNodeWhereLeft($childNode[$this->left_column_name]);
	}

	/**
	 * Same as insertNewChild except the new node is added as the last child
	 * @param array $parentNode The node array of the parent to use
	 * @param array $extrafields An associative array of fieldname=>value for the other fields in the recordset
	 * @return array $childNode An associative array representing the new node
	 */
	public function appendNewChild($parentNode, $extrafields = array()) {
		$childNode[$this->parent_column_name]	=	$parentNode[$this->primary_key_column_name];
		$childNode[$this->left_column_name]		=	$parentNode[$this->right_column_name];
		$childNode[$this->right_column_name]	=	$parentNode[$this->right_column_name]+1;

		$this->_modifyNode($childNode[$this->left_column_name], 2);
		$this->_setNewNode($childNode, $extrafields);

		return $this->getNodeWhereLeft($childNode[$this->left_column_name]);
	}
	/**
	 * Adds a new node to the left of the supplied focusNode
	 * @param array $focusNode The node to use as the position marker
	 * @param array $extrafields An associative array of node attributes
	 * @return array $siblingNode The new node
	 */
	public function insertSibling($focusNode, $extrafields) {
		$siblingNode[$this->parent_column_name]	=	$focusNode[$this->parent_column_name];
		$siblingNode[$this->left_column_name]	=	$focusNode[$this->left_column_name];
		$siblingNode[$this->right_column_name]	=	$focusNode[$this->left_column_name]+1;

		$this->_modifyNode($siblingNode[$this->left_column_name], 2);
		$this->_setNewNode($siblingNode, $extrafields);

		return $this->getNodeWhereLeft($siblingNode[$this->left_column_name]);
	}

	/**
	 * Adds a new node to the right of the supplied focusNode
	 * @param array $focusNode The node to use as the position marker
	 * @param array $extrafields An associative array of node attributes
	 * @return array $siblingNode The New Node
	 */
	public function appendSibling($focusNode, $extrafields) {
		$siblingNode[$this->parent_column_name]	=	$focusNode[$this->parent_column_name];
		$siblingNode[$this->left_column_name]	=	$focusNode[$this->right_column_name]+1;
		$siblingNode[$this->right_column_name]	=	$focusNode[$this->right_column_name]+2;

		$this->_modifyNode($siblingNode[$this->left_column_name], 2);
		$this->_setNewNode($siblingNode, $extrafields);

		return $this->getNodeWhereLeft($siblingNode[$this->left_column_name]);
	}


	/**
	 * Empties the table currently in use - use with extreme caution!
	 *
	 * @return boolean
	 */
	public function deleteTree() {
		return $this->db->delete($this->table_name);
	}

	/**
	 * Deletes the given node (and any children) from the tree table
	 * @param array $node The node to remove from the tree
	 * @return array $newnode The node that replaced the deleted node
	 */
	public function deleteNode($node) {
		$leftanchor		=	$node[$this->left_column_name];
		$leftcol		=	$this->left_column_name;
		$rightcol		=	$this->right_column_name;
		$leftval		=	$node[$this->left_column_name];
		$rightval		=	$node[$this->right_column_name];

		$where = array(
		$leftcol . ' >=' => $leftval,
		$rightcol . ' <=' => $rightval,
		);
		$this->db->delete($this->table_name, $where);

		$this->_modifyNode($node[$this->right_column_name]+1, $node[$this->left_column_name] -$node[$this->right_column_name] - 1);

		return $this->getNodeWhere($leftcol . ' < ' . $leftanchor . ' ORDER BY ' . $leftcol  . ' DESC');
	}

	// -------------------------------------------------------------------------
	//  MODIFY/REORGANISE TREE
	//
	//  Methods to move nodes around the tree. Method names should be
	//  relatively self-explanatory! Hopefully ;)
	//
	// -------------------------------------------------------------------------

	/**
	 * Moves the given node to make it the next sibling of "target"
	 * @param array $node The node to move
	 * @param array $target The node to use as the position marker
	 * @return array $newpos The new left and right values of the node moved
	 */
	public function setNodeAsNextSibling($node, $target) {
		$this->_setParent($node, $target[$this->parent_column_name]);
		return $this->_moveSubtree($node, $target[$this->right_column_name]+1);
	}

	/**
	 * Moves the given node to make it the prior sibling of "target"
	 * @param array $node The node to move
	 * @param array $target The node to use as the position marker
	 * @return array $newpos The new left and right values of the node moved
	 */
	public function setNodeAsPrevSibling($node, $target) {
		$this->_setParent($node, $target[$this->parent_column_name]);
		return $this->_moveSubtree($node, $target[$this->left_column_name]);
	}

	/**
	 * Moves the given node to make it the first child of "target"
	 * @param array $node The node to move
	 * @param array $target The node to use as the position marker
	 * @return array $newpos The new left and right values of the node moved
	 */
	public function setNodeAsFirstChild($node, $target) {
		$this->_setParent($node, $target[$this->primary_key_column_name]);
		return $this->_moveSubtree($node, $target[$this->left_column_name]+1);
	}

	/**
	 * Moves the given node to make it the last child of "target"
	 * @param array $node The node to move
	 * @param array $target The node to use as the position marker
	 * @return array $newpos The new left and right values of the node moved
	 */
	public function setNodeAsLastChild($node, $target) {
		$this->_setParent($node, $target[$this->primary_key_column_name]);
		return $this->_moveSubtree($node, $target[$this->right_column_name]);
	}

	// -------------------------------------------------------------------------
	//  QUERY METHODS
	//
	//  Selecting nodes from the tree
	//
	// -------------------------------------------------------------------------

	/**
	 * Selects the first node to match the given where clause argument
	 * @param mixed $whereArg Any valid SQL to follow the WHERE keyword in an SQL statement
	 * @return array $resultNode The node returned from the query
	 */
	public function getNodeWhere($whereArg = '"1"="1"') {
		$resultNode[$this->left_column_name]	=	$resultNode[$this->right_column_name]	=	0;

		$query = $this->db->get_where($this->table_name, $whereArg);

		$resultNode = array();
		if($query->num_rows() > 0)
		{
			$result = $query->result_array();
			$resultNode = array_shift($result); // assumes CI standard $row[0] = first row
		}

		return $resultNode;
	}

	/**
	 * Gets an array of nodes
	 *
	 * @param mixed $whereArg String or array of where arguments
	 * @param string $orderArg Orderby argument
	 * @param integer $limit_start Number of rows to retrieve
	 * @param mixed $limit_offset Row to start retrieving from
	 * @return array Returns array of nodes found
	 */
	public function getNodesWhere($whereArg = '"1"="1"', $orderArg = '', $limit_start = 0, $limit_offset = null) {
		$resultNode[$this->left_column_name]	=	$resultNode[$this->right_column_name]	=	0;

		if($orderArg) {
			$this->db->order_by($orderArg);
		}
		if($limit_start || $limit_offset) {
			$this->db->limit($limit_offset, $limit_start);
		}
		$query = $this->db->get_where($this->table_name, $whereArg);

		$resultNodes = array();
		if($query->num_rows() > 0)
		{
			$resultNodes = $query->result_array();
		}

		return $resultNodes;
	}

	/**
	 * Returns the node identified by the given left value
	 * @param integer $leftval The left value to use to select the node
	 * @return array $resultNode The node returned
	 */
	public function getNodeWhereLeft($leftval) {
		return $this->getNodeWhere($this->left_column_name . ' = ' . $leftval);
	}

	/**
	 * Returns the node identified by the given right value
	 * @param integer $rightval The right value to use to select the node
	 * @return array $resultNode The node returned
	 */
	public function getNodeWhereRight($rightval) {
		return $this->getNodeWhere($this->right_column_name . ' = ' . $rightval);
	}

	/**
	 * Returns the root nodes
	 * @return array $resultNode The node returned
	 */
	public function getRootNodes() {
		return $this->getNodesWhere($this->parent_column_name . ' = 0 ');
	}

	/**
	 * Returns the node with the appropriate primary key field value.
	 * Typically, this will be an auto_incrementing primary key column
	 * such as categoryid
	 * @param mixed $primarykey The value to look up in the primary key index
	 * @return array $resultNode The node returned
	 */
	public function getNodeFromId($primarykey) {
		// Test if we've set the primary key column name property
		if(empty($this->primary_key_column_name)) return false;

		return $this->getNodeWhere($this->primary_key_column_name . ' = "' . $primarykey . '"');
	}

	/**
	 * Returns the first child node of the given parentNode
	 * @param array $parentNode The parent node to use
	 * @return array $resultNode The first child of the parent node supplied
	 */
	public function getFirstChild($parentNode) {
		return $this->getNodeWhere($this->left_column_name . ' = ' . ($parentNode[$this->left_column_name]+1));
	}

	/**
	 * Returns the last child node of the given parentNode
	 * @param array $parentNode The parent node to use
	 * @return array $resultNode the last child of the parent node supplied
	 */
	public function getLastChild($parentNode) {
		return $this->getNodeWhere($this->right_column_name . ' = ' . ($parentNode[$this->right_column_name]-1));
	}

	/**
	 * Returns the node that is the immediately prior sibling of the given node
	 * @param array $currNode The node to use as the initial focus of enquiry
	 * @return array $resultNode The node returned
	 */
	public function getPrevSibling($currNode) {
		return $this->getNodeWhere($this->right_column_name . ' = ' . ($currNode[$this->left_column_name]-1));
	}

	/**
	 * Returns the node that is the next sibling of the given node
	 * @param array $currNode The node to use as the initial focus of enquiry
	 * @return array $resultNode The node returned
	 */
	public function getNextSibling($currNode) {
		return $this->getNodeWhere($this->left_column_name . ' = ' . ($currNode[$this->right_column_name]+1));
	}

	/**
	 * Returns the node that represents the parent of the given node
	 * @param array $currNode The node to use as the initial focus of enquiry
	 * @return array $resultNode the node returned
	 */
	public function getAncestor($currNode) {
		return $this->getNodeWhere($this->primary_key_column_name . ' = "' . $currNode[$this->parent_column_name] . '"');
	}


	// -------------------------------------------------------------------------
	//  NODE TEST METHODS
	//
	//  Boolean tests for nodes
	//
	// -------------------------------------------------------------------------


	/**
	 * Returns true or false
	 * (in reality, it checks to see if the given left and
	 * right values _appear_ to be valid not necessarily that they _are_ valid)
	 * @param array $node The node to test
	 * @return boolean
	 */
	public function checkIsValidNode($node) {
		return (empty($node) ? false : ($node[$this->left_column_name] < $node[$this->right_column_name]) );
	}

	/**
	 * Tests whether the given node has an ancestor
	 * (effectively the opposite of isRoot yes|no)
	 * @param array $node The node to test
	 * @return boolean
	 */
	public function checkNodeHasAncestor($node) {
		return $this->checkIsValidNode($this->getAncestor($node));
	}

	/**
	 * Tests whether the given node has a prior sibling or not
	 * @param array $node
	 * @return boolean
	 */
	public function checkNodeHasPrevSibling($node) {
		return $this->checkIsValidNode($this->getPrevSibling($node));
	}

	/**
	 * Test to see if node has siblings after itself
	 * @param array $node The node to test
	 * @return boolean
	 */
	public function checkNodeHasNextSibling($node) {
		return $this->checkIsValidNode($this->getNextSibling($node));
	}

	/**
	 * Test to see if node has children
	 * @param array $node The node to test
	 * @return boolean
	 */
	public function checkNodeHasChildren($node) {
		return (($node[$this->right_column_name] - $node[$this->left_column_name]) > 1);
	}

	/**
	 * Test to see if the given node is also a root node
	 * @param array $node The node to test
	 * @return boolean
	 */
	public function checkNodeIsRoot($node) {
		return ($node[$this->parent_column_name] == 0);
	}

	/**
	 * Test to see if the given node is a leaf node (ie has no children)
	 * @param array $node The node to test
	 * @return boolean
	 */
	public function checkNodeIsLeaf($node) {
		return (($node[$this->right_column_name] - $node[$this->left_column_name]) == 1);
	}

	/**
	 * Test to see if the first given node is a child of the second given node
	 * @param array $testNode the node to test for child status
	 * @param array $controlNode the node to use as the parent or ancestor
	 * @return boolean
	 */
	public function checkNodeIsChild($testNode, $controlNode) {
		return ($testNode[$this->parent_column_name] == $controlNode[$this->primary_key_column_name]);
	}

	/**
	 * Test to determine whether testNode is infact also controlNode (is A === B)
	 * @param array $testNode The node to test
	 * @param array $controlNode The node prototype to use for the comparison
	 * @return boolean
	 */
	public function checkNodeIsEqual($testNode, $controlNode) {
		return (($testNode[$this->left_column_name]==$controlNode[$this->left_column_name]) and ($testNode[$this->right_column_name]==$controlNode[$this->right_column_name]));
	}

	/**
	 * Combination method of IsChild and IsEqual
	 * @param array $testNode The node to test
	 * @param array $controlNode The node prototype to use for the comparison
	 * @return boolean
	 */
	public function checkNodeIsChildOrEqual($testNode, $controlNode) {
		return (($testNode[$this->left_column_name]>=$controlNode[$this->left_column_name]) and ($testNode[$this->right_column_name]<=$controlNode[$this->right_column_name]));
	}


	// -------------------------------------------------------------------------
	//  TREE QUERY METHODS
	//
	//  Query the tree itself
	//
	// -------------------------------------------------------------------------

	/**
	 * Returns the number of descendents that a node has
	 * @param array $node The node to query
	 * @return integer The number of descendents
	 */
	public function getNumberOfChildren($node) {
		return (($node[$this->right_column_name] - $node[$this->left_column_name] - 1) / 2);
	}

	/**
	 * Returns the tree level for the given node (assuming root node is at level 0)
	 * @param array $node The node to query
	 * @return integer The level of the supplied node
	 */
	public function getNodeLevel($node) {
		$leftcol	=	   $this->left_column_name;
		$rightcol   =	   $this->right_column_name;
		$leftval	= (int) $node[$leftcol];
		$rightval   = (int) $node[$rightcol];

		$this->db->where($leftcol . ' <', $leftval);
		$this->db->where($rightcol . ' >', $rightval);

		return $this->db->count_all($this->table_name);
	}

	/**
	 * Returns an array of the tree starting from the supplied node
	 * @param array $node The node to use as the starting point (typically root)
	 * @param boolean $direct When true, will retrieve only immediate children using parent col
	 * @return array $tree_handle The tree represented as an array to assist with
	 *							the other tree traversal operations
	 */
	public function getTreePreorder($node, $direct=false) {
		$leftcol	=	   $this->left_column_name;
		$rightcol   =	   $this->right_column_name;
		$leftval	= (int) $node[$leftcol];
		$rightval   = (int) $node[$rightcol];

		$primarykeycol	=		$this->primary_key_column_name;
		$parentcol		=		$this->parent_column_name;
		$primarykeyval	= (int) $node[$primarykeycol];

		if( $direct ) {
			$this->db->where($parentcol, $primarykeyval);
		}else{
			$this->db->where($leftcol . ' >=', $leftval);
			$this->db->where($rightcol . ' <=', $rightval);
		}
		$this->db->order_by($leftcol, 'asc');
		$query = $this->db->get($this->table_name);

		$treeArray = array();

		if($query->num_rows() > 0) {
			foreach($query->result_array() AS $result) {
				$treeArray[] = $result;
			}
		}

		$retArray = array(  'result_array'  =>	  $treeArray,
		'prev_left'	 =>	  $node[$leftcol],
		'prev_right'	=>	  $node[$rightcol],
		'level'		 =>	  -2);

		return $retArray;
	}

	/**
	 * Returns the next element from the tree and updates the tree_handle with the
	 * new positions
	 * @param array $tree_handle Passed by reference to allow for modifications
	 * @return array The next node in the tree
	 */
	public function getTreeNext($tree_handle) {
		$leftcol	=	$this->left_column_name;
		$rightcol	=	$this->right_column_name;

		if(!empty($tree_handle['result_array'])) {
			if($row = array_shift($tree_handle['result_array'])) {

				$tree_handle['level']+= $tree_handle['prev_left'] - $row[$leftcol] + 2;
				// store current node
				$tree_handle['prev_left']  = $row[$leftcol];
				$tree_handle['prev_right'] = $row[$rightcol];
				$tree_handle['row'] = $row;

				return array(   $leftcol  =>  $row[$leftcol],
				$rightcol =>  $row[$rightcol]
				);
			}
		}

		return FALSE;
	}

	/**
	 * Returns the given attribute (database field) for the current node in $tree_handle
	 * @param array $tree_handle The tree as an array
	 * @param string $attribute A string containing the fieldname to retrieve
	 * @return string The value requested
	 */
	public function getTreeAttribute($tree_handle,$attribute) {
		return $tree_handle['row'][$attribute];
	}

	/**
	 * Returns the current node of the tree contained in $tree_handle
	 * @param array $tree_handle The tree as an array
	 * @return array The left and right values of the current node
	 */
	public function getTreeCurrent($tree_handle) {
		return	array(
		$this->left_column_name		=>	$tree_handle['prev_left'],
		$this->right_column_name	=>	$tree_handle['prev_right'],
		);
	}

	/**
	 * Returns the current level from the tree
	 * @param array $tree_handle The tree as an array
	 * @return integer The integer value of the current level
	 */
	public function getTreeLevel($tree_handle) {
		return $tree_handle['level'];
	}

	/**
	 * Find the path of a given node
	 * @param array $node The node to start with
	 * @param boolean $includeSelf Wheter or not to include given node in result
	 * @param boolean $returnAsArray Wheter or not to return array or unordered list
	 * @return array or unordered list
	 */
	public function getPath($node, $includeSelf=FALSE, $returnAsArray=FALSE) {

		if(empty($node)) return FALSE;

		$leftcol	=	   $this->left_column_name;
		$rightcol   =	   $this->right_column_name;
		$leftval	= (int) $node[$leftcol];
		$rightval   = (int) $node[$rightcol];

		if($includeSelf)
		{
			$this->db->where($leftcol . ' <= ' . $leftval . ' AND ' . $rightcol . ' >= ' . $rightval);
		}
		else
		{
			$this->db->where($leftcol . ' < ' . $leftval . ' AND ' . $rightcol . ' > ' . $rightval);
		}

		$this->db->order_by($leftcol);
		$query = $this->db->get($this->table_name);

		if($query->num_rows() > 0)
		{
			if($returnAsArray)
			{
				return $query->result_array();
			}
			else
			{
				return $this->buildCrumbs($query->result_array());
			}
		}

		return FALSE;
	}

	function buildCrumbs($crumbData)
	{
		$retVal = '';

		$retVal = '<ul id="breadcrumbs">';

		foreach ($crumbData as $itemId)
		{
			if($itemId['id'] > 1) $retVal .= '<span class="divider">></span>';

			$retVal .= '<li>' . anchor(
				'shop/category/' . $itemId['id'],
				$itemId[$this->text_column_name],
				array(
					'name' => $itemId[$this->text_column_name])
				);

			$retVal .= '</li>';
		}

		$retVal .= '</ul>';

		return $retVal;
	}


	// -------------------------------------------------------------------------
	//   NODE FIELD QUERIES
	//
	// -------------------------------------------------------------------------

	/**
	 * Queries the database for the value of the given field
	 * @param array $node The node to be queried
	 * @param string $fieldname The name of the field to query
	 * @return string $retval The value of the field for the node looked up
	 */
	public function getNodeAttribute($node, $fieldname) {
		$leftcol	=		$this->left_column_name;
		$leftval	= (int) $node[$leftcol];

		$this->db->where($leftcol, $leftval);
		$query = $this->db->get($this->table_name);

		if($query->num_rows() > 0) {
			$res = $query->result();
			return $res->$fieldname;
		} else {
			return '';
		}
	}

	/**
	 * Renders the fields for each node starting at the given node
	 * @param array $node The node to start with
	 * @param array $fields The fields to display for each node
	 * @return string Sample HTML render of tree
	 */
	public function getSubTreeAsHTML($nodes, $fields = array()) {
		if(isset($nodes[0]) && !is_array($nodes[0])) {
			$nodes = array($nodes);
		}

		$retVal = '';
		foreach($nodes AS $node) {
			$tree_handle = $this->getTreePreorder($node);

			while($this->getTreeNext($tree_handle))
			{
				// print indentation
				$retVal .= (str_repeat('&nbsp;', $this->getTreeLevel($tree_handle)*4));

				// print requested fields
				$field = reset($fields);
				while($field){
					$retVal .= $tree_handle['row'][$field] . "\n";
					$field = next($fields);
				}
				$retVal .= "<br />\n";

			}
		}

		return $retVal;
	}

	/**
	 * Renders the tree starting from given node
	 * @param array $node The node to start with
	 * @return string Unordered HTML list of the tree
	 */
	public function getSubTree($node) {

		if(empty($node)) return FALSE;

		$tree_handle = $this->getTreePreorder($node);

		$menuData = array(
			'items' => array(),
			'parents' => array()
		);

		foreach ($tree_handle['result_array'] as $menuItem)
		{
			$menuData['items'][$menuItem['id']] = $menuItem;
			$menuData['parents'][$menuItem['parent_id']][] = $menuItem['id'];
		}

		return $menuData;
		// return $this->buildMenu($node['parent_id'], $menuData);
	}

	function buildMenu($parentId, $menuData, $depth=0)
	{
		$retVal = '';

		if (isset($menuData['parents'][$parentId]))
		{
			$retVal = '<ul>';

			foreach ($menuData['parents'][$parentId] as $itemId)
			{

				$retVal .= '<li class="depth-' . $depth . '">' . anchor(
					'shop/category/' . $menuData['items'][$itemId]['id'],
					$menuData['items'][$itemId][$this->text_column_name],
					array(
						'class' => 'id-' . $itemId['id']
					)
				);

				$retVal .= $this->buildMenu($itemId, $menuData, $depth+1);

				$retVal .= '</li>';
			}

			$retVal .= '</ul>';
		}

		return $retVal;
	}


	/**
	 * Renders the entire tree as per getSubTreeAsHTML starting from root
	 * @param array $fields An array of the fields to display
	 */
	public function getTreeAsHTML($fields=array()) {
		return $this->getSubTreeAsHTML($this->getRootNodes(), $fields);
	}

	// -------------------------------------------------------------------------
	//  INTERNALS
	//
	//  Private, internal methods
	//
	// -------------------------------------------------------------------------

	/**
	 *  _setNewNode
	 *
	 *  Inserts a new node into the tree
	 *
	 *  @param array $node An array containing the left and right values to use
	 *  @param array $extrafields An associative array of field names to values for \
	 *						  additional columns in tree table (eg CategoryName etc)
	 *
	 *  @return boolean True/False dependent upon the success of the operation
	 *  @access private
	 */
	private function _setNewNode($node, $extrafields) {
		$parentcol	=		$this->parent_column_name;
		$leftcol	=		$this->left_column_name;
		$rightcol	=		$this->right_column_name;
		$parentval	= (int) $node[$parentcol];
		$leftval	= (int) $node[$leftcol];
		$rightval	= (int) $node[$rightcol];

		$data = array(
			$parentcol => $parentval,
			$leftcol => $leftval,
			$rightcol => $rightval,
		);
		if(is_array($extrafields) && !empty($extrafields)) $data = array_merge($data, $extrafields);

		$result = $this->db->insert($this->table_name, $data);

		if(!$result) {
			log_message('error', 'Node addition failed for ' . $leftval . ' - ' . $rightval);
		}

		return $result;
	}

	/**
	 * Sets the parent for the specified node
	 *
	 * @param array $node The child node
	 * @param integer $parent_id The id of the parent node
	 * @access private
	 */
	private function _setParent($node, $parent_id) {
		$primarykeycol	=	$this->primary_key_column_name;
		$parentcol		=	$this->parent_column_name;
		$primarykeyval	=	(int) $node[$primarykeycol];
		$parentval		=	(int) $node[$parentcol];

		if($parentval != $parent_id) {
			$data = array(
				$parentcol => $parent_id,
			);

			$this->db->where($primarykeycol, $primarykeyval);
			$this->db->update($this->table_name, $data);
		}
	}

	/**
	 * The method that performs moving/renumbering operations
	 *
	 * @param array $node The node to move
	 * @param array $targetValue Position integer to use as the target
	 * @return array $newpos The new left and right values of the node moved
	 * @access private
	 */
	private function _moveSubtree($node, $targetValue) {
		$sizeOfTree = $node[$this->right_column_name] - $node[$this->left_column_name] + 1;
		$this->_modifyNode($targetValue, $sizeOfTree);

		if($node[$this->left_column_name] >= $targetValue)
		{
			$node[$this->left_column_name] += $sizeOfTree;
			$node[$this->right_column_name] += $sizeOfTree;
		}

		$newpos = $this->_modifyNodeRange($node[$this->left_column_name], $node[$this->right_column_name], $targetValue - $node[$this->left_column_name]);

		$this->_modifyNode($node[$this->right_column_name]+1, - $sizeOfTree);

		if($node[$this->left_column_name] <= $targetValue)
		{
			$newpos[$this->left_column_name] -= $sizeOfTree;
			$newpos[$this->right_column_name] -= $sizeOfTree;
		}

		return $newpos;
	}

	/**
	 * _modifyNode
	 *
	 * Adds $changeVal to all left and right values that are greater than or
	 * equal to $node_int
	 *
	 * @param integer $node_int The value to start the shift from
	 * @param integer $changeVal unsigned integer value for change
	 * @access private
	 */
	private function _modifyNode($node_int, $changeVal) {
		$leftcol	=	$this->left_column_name;
		$rightcol	=	$this->right_column_name;

		$this->db->set($leftcol, $leftcol . '+' . (int) $changeVal, FALSE);
		$this->db->where($leftcol . ' >=', (int) $node_int);
		$this->db->update($this->table_name);

		$this->db->set($rightcol, $rightcol . '+' . (int) $changeVal, FALSE);
		$this->db->where($rightcol . ' >=', (int) $node_int);
		$this->db->update($this->table_name);
	}

	/**
	 * _modifyNodeRange
	 *
	 * @param integer $lowerbound integer value of lowerbound of range to move
	 * @param integer $upperbound integer value of upperbound of range to move
	 * @param integer $changeVal unsigned integer of change amount
	 * @return array Returns array of the new left & right column values
	 * @access private
	 */
	private function _modifyNodeRange($lowerbound, $upperbound, $changeVal) {
		$leftcol	=	$this->left_column_name;
		$rightcol	=	$this->right_column_name;

		$this->db->set($leftcol, $leftcol . '+' . (int) $changeVal, FALSE);
		$this->db->where($leftcol . ' >=', (int) $lowerbound);
		$this->db->where($leftcol . ' <=', (int) $upperbound);
		$this->db->update($this->table_name);

		$this->db->set($rightcol, $rightcol . '+' . (int) $changeVal, FALSE);
		$this->db->where($rightcol . ' >=', (int) $lowerbound);
		$this->db->where($rightcol . ' <=', (int) $upperbound);
		$this->db->update($this->table_name);

		$retArray = array(
		$this->left_column_name  =>  $lowerbound+$changeVal,
		$this->right_column_name =>  $upperbound+$changeVal
		);
		return $retArray;
	}

}

?>
