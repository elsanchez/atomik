<?php
/**
 * Atomik Framework
 * Copyright (c) 2008 Maxime Bouroumeau-Fuseau
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package Atomik
 * @subpackage Model
 * @author Maxime Bouroumeau-Fuseau
 * @copyright 2008 (c) Maxime Bouroumeau-Fuseau
 * @license http://www.opensource.org/licenses/mit-license.php
 * @link http://www.atomikframework.com
 */

/** Atomik_Model_Builder */
require_once 'Atomik/Model/Builder.php';

/**
 * @package Atomik
 * @subpackage Model
 */
class Atomik_Model
{
	/**
	 * The model builder attached to this model
	 *
	 * @var Atomik_Model_Builder
	 */
	protected $_builder;
	
	/**
	 * Whether the model is a new entry
	 *
	 * @var bool
	 */
	protected $_new = true;
	
	/**
	 * References cache
	 *
	 * @var array
	 */
	protected $_references = array();
	
	/**
	 * Finds many models
	 *
	 * @param string|Atomik_Model_Builder $model
	 * @param array $where
	 * @param string $orderBy
	 * @param string $limit
	 * @return array
	 */
	public static function findAll($model, $where = null, $orderBy = '', $limit = '')
	{
		if (! $model instanceof Atomik_Model_Builder) {
			$model = new Atomik_Model_Builder($model);
		}
		
		return $model->getAdapter()->findAll($model, $where);
	}
	
	/**
	 * Finds one model
	 *
	 * @param string|Atomik_Model_Builder $model
	 * @param array $where
	 * @param string $orderBy
	 * @param string $limit
	 * @return Atomik_Model
	 */
	public static function find($model, $where, $orderBy = '', $limit = '')
	{
		if (! $model instanceof Atomik_Model_Builder) {
			$model = new Atomik_Model_Builder($model);
		}
		
		return $model->getAdapter()->find($model, $where, $orderBy = '', $limit = '');
	}
	
	/**
	 * Constructor
	 *
	 * @param array $data OPTIONAL
	 * @param bool $new OPTIONAL
	 */
	public function __construct($data = array(), $new = true)
	{
		$this->_new = $new;
		$this->setData($data);
		
	}
	
	/**
	 * Sets the builder attached to this model
	 *
	 * @param Atomik_Model_Builder $builder
	 */
	public function setBuilder(Atomik_Model_Builder $builder = null)
	{
		if ($builder === null) {
			$this->_builder = new Atomik_Model_Builder($this);
		} else {
			$this->_builder = $builder;
		}
	}
	
	/**
	 * Gets the builder attached to this model
	 *
	 * @return Atomik_Model_Builder
	 */
	public function getBuilder()
	{
		if ($this->_builder === null) {
			$this->setBuilder();
		}
		return $this->_builder;
	}
	
	/**
	 * Checks if the model is new
	 *
	 * @return bool
	 */
	public function isNew()
	{
		return $this->_new;
	}
	
	/**
	 * Sets data
	 *
	 * @param array $data
	 */
	public function setData($data)
	{
		$fields = $this->getBuilder()->getMetadata('fields', array());
		foreach ($fields as $field) {
			if (isset($data[$field['name']])) {
				$this->{$field['property']} = $data[$field['name']];
			}
		}
	}
	
	/**
	 * Get accessor (to handle reference properties)
	 *
	 * @param string $name
	 */
	public function __get($name)
	{
		/* checks if it's in cache */
		if (isset($this->_references[$name])) {
			return $this->_references[$name];
		}
		
		list($ref, $orig, $dest) = $this->_getReferenceInfo($name);
		$where = array($dest['field'] => $this->{$orig['field']});
		
		if ($ref['type'] == 'has-many') {
			$this->_references[$name] = self::findAll($dest['model'], $where);
		} else {
			$this->_references[$name] =  self::find($dest['model'], $where);
		}
		
		return $this->_references[$name];
	}
	
	/**
	 * Set accessor to handle references
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		list($ref, $orig, $dest) = $this->_getReferenceInfo($name);
		
		if ($ref['type'] == 'has-many') {
			/* has many relation */
			$this->removeAll($name);
			
			/* value must be an array */
			$values = !is_array($value) ? array($value) : $value;
			foreach ($values as $value) {
				/* sets the foreign key of the child model */
				$value->{$dest['field']} = $this->{$orig['field']};
			}
			
			$this->_references[$name] = $values;
			
		} else {
			/* has one relation */
			/* sets the foreign key of the child model */
			$value->{$dest['field']} = $this->{$orig['field']};
			$this->_references[$name] = $value;
		}
	}
	
	/**
	 * Checks if the model is a child model from an has many reference
	 *
	 * @param Atomik_Model $model
	 * @param string $property OPTIONAL
	 * @return bool
	 */
	public function contains(Atomik_Model $model, $property = null)
	{
		list($ref, $orig, $dest) = $this->_getReferenceInfo($model, $property);
		
		if ($ref['type'] != 'has-many') {
			require_once 'Atomik/Model/Exception.php';
			throw new Atomik_Model_Exception('Atomik_Model::add() can only'
				. ' be used with has many relations');
		}
		
		/* inits the reference array */
		if (!isset($this->_references[$property])) {
			$this->__get($property);
		}
		
		foreach ($this->_references[$property] as $child) {
			if ($child === $model) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Adds a model to an has many reference
	 *
	 * @param Atomik_Model $model
	 * @return Atomik_Model;
	 */
	public function add(Atomik_Model $model, $property = null)
	{
		list($ref, $orig, $dest) = $this->_getReferenceInfo($model, $property);
		
		if ($ref['type'] != 'has-many') {
			require_once 'Atomik/Model/Exception.php';
			throw new Atomik_Model_Exception('Atomik_Model::add() can only'
				. ' be used with has many relations');
		}
		
		/* sets the foreign key on the child model */
		$model->{$dest['field']} = $this->{$orig['field']};
		
		/* inits the reference array */
		if (!isset($this->_references[$property])) {
			$this->__get($property);
		}
		
		$this->_references[$property][] = $model;
		return $model;
	}
	
	/**
	 * Removes a model from an has many reference
	 *
	 * @param Atomik_Model $model
	 * @param string $property OPTIONAL
	 * @return Atomik_Model|bool The removed model or false if failed
	 */
	public function remove(Atomik_Model $model, $property = null)
	{
		list($ref, $orig, $dest) = $this->_getReferenceInfo($model, $property);
	
		if ($ref['type'] != 'has-many') {
			require_once 'Atomik/Model/Exception.php';
			throw new Atomik_Model_Exception('Atomik_Model::remove() can only'
				. ' be used with has many relations');
		}
	
		/* inits the reference array */
		if (!isset($this->_references[$property])) {
			$this->__get($property);
		}
		
		for ($i = 0, $c = count($this->_references[$property]); $i < $c; $i++) {
			$child = $this->_references[$property][$i];
			if ($child === $model) {
				$child->{$dest['field']} = null;
				unset($this->_references[$property][$i]);
				return $child;
			}
		}
		
		return false;
	}
	
	/**
	 * Removes all child from an has many reference
	 * WARNING: All removed child are automatically saved
	 *
	 * @param string $property
	 */
	public function removeAll($property)
	{
		list($ref, $orig, $dest) = $this->_getReferenceInfo($property);
	
		if ($ref['type'] != 'has-many') {
			require_once 'Atomik/Model/Exception.php';
			throw new Atomik_Model_Exception('Atomik_Model::removeAll() can only'
				. ' be used with has many relations');
		}
	
		/* inits the reference array */
		if (!isset($this->_references[$property])) {
			$this->__get($property);
		}
		
		foreach ($this->_references[$property] as $child) {
			$child->{$dest['field']} = null;
			$child->save();
		}
		$this->_references[$property] = array();
	}
	
	/**
	 * Invalidates the reference cache
	 */
	public function invalidateReferenceCache()
	{
		$this->_references = array();
	}
	
	/**
	 * Saves
	 *
	 * @return bool Success
	 */
	public function save()
	{
		return $this->getBuilder()->getAdapter()->save($this);
	}
	
	/**
	 * Deletes
	 *
	 * @return bool Success
	 */
	public function delete()
	{
		if ($this->getBuilder()->getAdapter()->delete($this)) {
			$this->_new = true;
			return true;
		}
		return false;
	}
	
	/**
	 * Transforms the model to an array
	 *
	 * @return array
	 */
	public function toArray()
	{
		$data = array();
		$fields = $this->getBuilder()->getMetadata('fields', array());
		foreach ($fields as $field) {
			$data[$field['name']] = $this->{$field['property']};
		}
		return $data;
	}
	
	/**
	 * Gets information about the reference
	 *
	 * @param string|Atomik_Model $name
	 * @param string $property OPTIONAL
	 * @return array
	 */
	protected function _getReferenceInfo($name, &$property = null)
	{
		$references = $this->getBuilder()->getMetadata('references', array());
		
		if ($name instanceof Atomik_Model) {
			if ($property === null) {
				foreach ($references as $prop => $ref) {
					if ($ref['model'] == get_class($name)) {
						$property = $prop;
						break;
					}
				}
			}
			$name = $property;
		}
		
		if (!isset($references[$name])) {
			require_once 'Atomik/Model/Exception.php';
			throw new Atomik_Model_Exception('Property ' . get_class($this) . '::' . 
				$name . ' does not exists');
		}
		$ref = $references[$name];
		
		if ($ref['using'][0]['model'] == get_class($this)) {
			$dest = $ref['using'][1];
			$orig = $ref['using'][0];
		} else {
			$dest = $ref['using'][0];
			$orig = $ref['using'][1];
		}
		
		return array($ref, $orig, $dest);
	}
}