<?php

class Atomik_Auth
{
	/**
	 * @var Atomik_Auth_Backend_Interface
	 */
	private static $_backend;
	
	/**
	 * @var Atomik_Auth_User
	 */
	private static $_currentUser;
	
	/**
	 * @var array
	 */
	private static $_roles = array();
	
	/**
	 * @var array
	 */
	private static $_resources = array();
	
	/**
	 * Sets the login backend
	 * 
	 * @var Atomik_Auth_Backend_Interface $backend
	 */
	public static function setBackend(Atomik_Auth_Backend_Interface $backend)
	{
		self::$_backend = $backend;
	}
	
	/**
	 * Returns the login backend
	 * 
	 * @return Atomik_Auth_Backend_Interface
	 */
	public static function getBackend()
	{
		if (self::$_backend === null) {
			throw new Atomik_Auth_Exception('A backend must be specified');
		}
		return self::$_backend;
	}
	
	/**
	 * Authentify a user
	 * 
	 * @param 	string					$username
	 * @param 	string					$password
	 * @param 	bool|int				$remember	Whether to remember the user for next logins, true for 2 weeks, or set any time in seconds
	 * @return 	Atomik_Auth_User|bool				The user object of false if it fails
	 */
	public static function login($username, $password, $remember = false)
	{
		$backend = self::getBackend();
		if (($user = $backend->authentify($username, $password)) === false) {
			return false;
		}
		
		self::$_currentUser = $_SESSION['__USER'] = $username;
		
		if ($remember !== false) {
			if ($remember === true) {
				$remember = 1209600;
			}
			session_set_cookie_params(time() + $remember);
		} else {
			session_set_cookie_params(time() + 3600);
		}
		
		return true;
	}
	
	/*
	 * Logout the current user
	 */
	public static function logout()
	{
		if (isset($_SESSION['__USER'])) {
			unset($_SESSION['__USER']);
		}
		self::$_currentUser = null;
	}
	
	/**
	 * Checks if there's a logged in user
	 * 
	 * @return bool
	 */
	public static function isLoggedIn()
	{
		return self::$_currentUser !== null;
	}
	
	/**
	 * Returns the current logged in user object
	 * 
	 * @return Atomik_Auth_User
	 */
	public static function getCurrentUser()
	{
		if (self::$_currentUser === null && isset($_SESSION['__USER'])) {
			self::$_currentUser = self::getBackend()->getUser($_SESSION['__USER']);
		}
		return self::$_currentUser;
	}
	
	/**
	 * Resets all roles
	 * 
	 * @param array $roles
	 */
	public static function setRoles($roles)
	{
		self::$_roles = self::computeRoles($roles);
	}
	
	/**
	 * Creates a new role
	 * 
	 * @param	string			$role
	 * @param	string|array	$parentRoles
	 */
	public static function addRole($role, $parentRoles = array())
	{
		self::$_roles[(string) $role] = array_keys(self::computeRoles($parentRoles));
	}
	
	/**
	 * Sets the parent roles of a specific role
	 * 
	 * @param	string			$role
	 * @param	string|array	$parentRoles
	 * @param	bool			$overwrite
	 */
	public static function setRoleParents($role, $parentRoles, $overwrite = true)
	{
		$role = (string) $role;
		$parentRoles = array_keys(self::computeRoles($parentRoles));
		
		if (!isset(self::$_roles[$role])) {
			self::$_roles[$role] = array();
		}
		
		if ($overwrite) {
			self::$_roles[$role] = $parentRoles;
		} else {
			self::$_roles[$role] = array_merge(self::$_roles[$role], $parentRoles);
		}
	}
	
	/**
	 * Returns the parent roles of a specific role
	 * 
	 * @param	string|array	$roles	If an array is used, all parent roles of all specified roles will be returned
	 * @return	array					All parent roles
	 */
	public static function getRoleParents($roles)
	{
		$roles = (array) $roles;
		$parents = array();
		
		foreach ($roles as $role) {
			if (!isset(self::$_roles[$role])) {
				continue;
			}
			$parents = array_merge($parents, self::$_roles[$role]);
		}
		
		return $parents;
	}
	
	/**
	 * Recursively creates all roles in the array
	 * 
	 * Won't override any existing roles
	 * 
	 * @param	array	$roles
	 * @return 	array			Returns a formated array of all roles
	 */
	private static function computeRoles($roles)
	{
		$roles = self::formatRolesArray($roles);
		$computedRoles = array();
		
		foreach ($roles as $role => $parentRoles) {
			if (!array_key_exists($role, self::$_roles)) {
				self::addRole($role, $parentRoles);
			}
			$computedRoles[$role] = array_keys(self::formatRolesArray($parentRoles));
		}
		
		return $computedRoles;
	}
	
	/**
	 * Formats a roles array using role names as keys and parent roles as values
	 * 
	 * @param	array	$roles
	 * @return	array
	 */
	private static function formatRolesArray($roles)
	{
		$roles = (array) $roles;
		$formatedRoles = array();
		
		foreach ($roles as $role => $parentRoles) {
			if (!is_string($role)) {
				$formatedRoles[$parentRoles] = array();
			} else {
				$formatedRoles[$role] = $parentRoles;
			}
		}
		
		return $formatedRoles;
	}
	
	/**
	 * Removes all specified roles
	 * 
	 * @param array|string $roles
	 */
	public static function removeRole($roles)
	{
		$roles = (array) $roles;
		foreach ($roles as $role) {
			if (isset(self::$_roles[(string) $role])) {
				unset(self::$_roles[(string) $role]);
			}
		}
	}
	
	/**
	 * Returns all roles
	 * 
	 * @return array
	 */
	public static function getRoles()
	{
		return array_keys(self::$_roles);
	}
	
	/**
	 * Resets all resources
	 * 
	 * @param array $resources
	 */
	public static function setResources($resources)
	{
		self::$_resources = array();
		foreach ($resources as $resource => $roles) {
			if (is_integer($resources)) {
				self::addResource($roles);
			} else {
				self::addResource($resource, $roles);
			}
		}
	}
	
	/**
	 * Creates a new resource and associated roles to it
	 * 
	 * @param	string	$resource
	 * @param	array	$roles
	 */
	public static function addResource($resource, $roles = array())
	{
		self::$_resources[(string) $resource] = array_keys(self::computeRoles($roles));
	}
	
	/**
	 * Removes a resource
	 * 
	 * @param string|array $resources
	 */
	public static function removeResource($resources)
	{
		$resources = (array) $resources;
		foreach ($resources as $resource) {
			if (isset(self::$_resources[(string) $resource])) {
				unset(self::$_resources[(string) $resource]);
			}
		}
	}
	
	/**
	 * Returns all resources
	 * 
	 * @return array
	 */
	public static function getResources()
	{
		return array_keys(self::$_resources);
	}
	
	/**
	 * Sets roles associated to a resource
	 * 
	 * @param	string			$resource
	 * @param 	array|string	$roles
	 * @param	bool			$overwrite
	 */
	public static function setResourceRoles($resource, $roles, $overwrite = true)
	{
		$resource = (string) $resource;
		$roles = array_keys(self::computeRoles($roles));
		
		if (!isset(self::$_resources[$resource])) {
			self::$_resources[$resource] = array();
		}
		
		if ($overwrite) {
			self::$_resources[$resource] = $roles;
		} else {
			self::$_resources[$resource] = array_merge(self::$_resources[$resource], $roles);
		}
	}
	
	/**
	 * Removes a role associated to a resource
	 * 
	 * @param	string			$resource
	 * @param	string|array	$roles
	 */
	public static function removeRoleFromResource($resource, $roles)
	{
		$resource = (string) $resource;
		if (!isset(self::$_resources[$resource])) {
			return;
		}
		
		$roles = (array) $roles;
		foreach ($roles as $role) {
			for ($i = 0, $c = count(self::$_resources[$resource]); $i < $c; $i++) {
				if (self::$_resources[$resource][$i] == $role) {
					unset(self::$_resources[$resource][$i]);
					break;
				}
			}
		}
	}
	
	/**
	 * Returns all roles associated to the specified resources
	 * 
	 * @param	string|array	$resources
	 * @return 	array
	 */
	public static function getResourceRoles($resources)
	{
		$resources = (array) $resources;
		$roles = array();
		
		foreach ($resources as $resource) {
			if (isset(self::$_resources[(string) $resource])) {
				$roles = array_merge($roles, self::$_resources[(string) $resource]);
			}
		}
		
		return $roles;
	}
	
	/**
	 * Alias for Atomik_Auth::setResources()
	 * 
	 * @see Atomik_Auth::setResources()
	 * @param array $resources
	 */
	public static function setAcl($resources)
	{
		self::setResources($resources);
	}
	
	/**
	 * Returns all resources and their associated roles
	 * 
	 * @return array
	 */
	public static function getAcl()
	{
		return self::$_resources;
	}
	
	/**
	 * Checks if the specified roles can access a resource
	 * 
	 * @param	string 			$resource
	 * @param 	string|array	$roles
	 * @return 	bool
	 */
	public static function isAllowed($resource, $roles)
	{
		$roles = (array) $roles;
		$resourceRoles = self::getResourceRoles($resource);
		
		foreach ($resourceRoles as $resourceRole) {
			if (!self::checkRole($resourceRole, $roles)) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Checks if a role is contained in a role array. Also checks in role parents.
	 * 
	 * @param	string 	$neededRole
	 * @param 	array	$availableRoles
	 * @return  bool
	 */
	public static function checkRole($neededRole, $availableRoles)
	{
		if (count($availableRoles) == 0) {
			return false;
		} else if (in_array($neededRole, $availableRoles)) {
			return true;
		}
		
		return self::checkRole($neededRole, self::getRoleParents($availableRoles));
	}
}