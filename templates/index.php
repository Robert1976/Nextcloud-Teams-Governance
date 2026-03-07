<?php

declare(strict_types=1);

use OCP\Util;

$mainJsPath = __DIR__ . '/../js/teamsgovernance-main.js';
$mainMjsPath = __DIR__ . '/../js/teamsgovernance-main.mjs';
$mainCssPath = __DIR__ . '/../css/teamsgovernance-main.css';
$mainJsExists = file_exists($mainJsPath) || file_exists($mainMjsPath);
$mainCssExists = file_exists($mainCssPath);

if ($mainJsExists) {
	Util::addScript(OCA\TeamsGovernance\AppInfo\Application::APP_ID, OCA\TeamsGovernance\AppInfo\Application::APP_ID . '-main');
}

if ($mainCssExists) {
	Util::addStyle(OCA\TeamsGovernance\AppInfo\Application::APP_ID, OCA\TeamsGovernance\AppInfo\Application::APP_ID . '-main');
}

?>

<div id="teamsgovernance"></div>
