<?php namespace surikat\control\GitDeploy;
abstract class GitDeploy{
	static function main(){
		ini_set('memory_limit', '256M');
		$args = Config::getArgs();
		$servers = Config::getServers($args['config_file']);
		$git = new Git($args['repo_path']);

		foreach ($servers as $server) {
			if ($args['revert']) {
				$server->revert($git, $args['list_only']);
			} else {
				$server->deploy($git, $git->interpret_target_commit($args['target_commit'], $server->server['branch']), false, $args['list_only']);
			}
		}
	}

	static function logmessage($message) {
		static $log_handle = null;

		$log = "[" . @date("Y-m-d H:i:s O") . "] " . $message . PHP_EOL;
		if (defined("WRITE_TO_LOG")) {
			if ($log_handle === null) {
				$log_handle = fopen(WRITE_TO_LOG, 'a');
			}

			fwrite($log_handle, $log);
		}

		echo $log;
	}

	static function error($message){
		self::logmessage("ERROR: $message");
		die;
	}

	static function get_recursive_file_list($folder, $prefix = '') {

		# Add trailing slash
		$folder = (substr($folder, strlen($folder) - 1, 1) == '/') ? $folder : $folder . '/';

		$return = array();

		foreach (clean_scandir($folder) as $file) {
			if (is_dir($folder . $file)) {
				$return = array_merge($return, self::get_recursive_file_list($folder . $file, $prefix . $file . '/'));
			} else {
				$return[] = $prefix . $file;
			}
		}

		return $return;
	}

	static function clean_scandir($folder, $ignore = array()) {
		$ignore[] = '.';
		$ignore[] = '..';
		$ignore[] = '.DS_Store';
		$return = array();

		foreach (scandir($folder) as $file) {
			if (!in_array($file, $ignore)) {
				$return[] = $file;
			}
		}

		return $return;
	}
}