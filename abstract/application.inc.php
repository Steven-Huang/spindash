<?php

/**
* SpinDash — A web development framework
* © 2007–2013 Ilya I. Averkov <admin@jsmart.web.id>
*
* Contributors:
* Irfan Mahfudz Guntur <ayes@bsmsite.com>
* Evgeny Bulgakov <evgeny@webline-masters.ru>
*/

namespace SpinDash;

abstract class Application extends API implements IApplication
{
	// Left for compatibility
	protected $sd = NULL;
	protected $ats = NULL;
	
	private $settings = [];
	private $modules = [];
	
	public function __construct($configuration_file_name) {
		
		if(PHP_SAPI == 'cli') {
			global $argv;
			parent::__construct(API::FRONTEND_FASTCGI);
		} else {
			parent::__construct(API::FRONTEND_BASIC);
		}
		
		$this->initializeConfiguration($configuration_file_name);
		
		if(method_exists($this, 'routeMap')) {
			parent::registerCommonRequestHandler($this, 'initializeDynamicRouteTable');
		}
		
		// Left for compatibility
		$this->sd = $this->ats = $this;
	}
	
	private function initializeConfiguration($configuration_file_name) {
		if(!parent::isIncludeable($configuration_file_name)) {
			throw new CoreException("Configuration file <$configuration_file_name> is not includeable");
		}
		$settings = & $this->settings;
		require_once $configuration_file_name;
		
		if(isset($settings['paths']['layout']['directory'])) {
			parent::useLayoutDirectory($settings['paths']['layout']['directory'], @ $settings['paths']['layout']['webpath']);
			parent::layout()->setDefaultExtension($settings['paths']['layout']['filename_extension']);
		}
		
		if(isset($settings['database'])) {
			switch($settings['database']['engine']) {
				default: throw new CoreException("Unknown database engine in config.inc.php <{$settings['database']['engine']}>"); break;
				case 'MySQL':
					parent::useMySQL($settings['database']['hostname'], $settings['database']['username'], $settings['database']['password'], $settings['database']['name']);
				break;
				case 'PostgreSQL':
					parent::usePostgreSQL($settings['database']['hostname'], $settings['database']['username'], $settings['database']['password'], $settings['database']['name']);
				break;
				case 'SQLite':
					parent::useSQLite($settings['database']['filename']);
				break;
			}
		}
		
		if(isset($settings['cache'])) {
			switch(@ $settings['cache']['engine']) {
				default: throw new CoreException("Unknown caching engine in config.inc.php <{$settings['database']['engine']}>"); break;
				case 'Database':
					parent::useDatabaseCache($settings['cache']['key_prefix']);
				break;
				case 'Memcached':
					parent::useMCCache($settings['cache']['hostname'], $settings['cache']['port'], $settings['cache']['key_prefix']);
				break;
				case 'Redis':
					parent::useRedisCache($settings['cache']['hostname'], $settings['cache']['port'], $settings['cache']['key_prefix']);
				break;
			}
		}
	}
	
	protected function initializeDynamicRouteTable(Request $request) {
		$methods = call_user_func([$this, 'routeMap'], $request);
		foreach($methods as $method => $routes) {
			foreach($routes as $route => $handler) {
				call_user_func([$this, $method], $route, $this, $handler);
			}
		}
	}
	
	public function settings($section = NULL) {
		if(is_null($section)) {
			return $this->settings;
		} else {
			if(!array_key_exists($section, $this->settings)) {
				throw new CoreException("Unknown settings key: $section");
			}
			return $this->settings[$section];
		}
	}
	
	public function moduleInstance($module_name) {
		if(!array_key_exists($module_name, $this->modules)) {
			throw new CoreException("Unknown Spin Dash pluggable module: &lt;$module_name&gt;");
		}
		return $this->modules[$module_name];
	}
	
	public function registerModule(Module $module) {
		$module_name = get_class($module);
		if(array_key_exists($module_name, $this->modules)) {
			throw new CoreException("Spin Dash pluggable module &lt;$module_name&gt; has already been initialized");
		}
		
		$this->modules[$module_name] = $module;
	}
}
