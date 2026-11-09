<?php

use ComposerUnused\ComposerUnused\Configuration\Configuration;

// The plugin has no Composer autoload section (Kirby loads src/ through
// autoload.php), so the files to scan are named here. With them, the PHP
// requirement and the installer count as used as well.
return static function (Configuration $configuration): Configuration {
	return $configuration
		->setAdditionalFilesFor('sayamaapps/kirby-monban', glob(__DIR__ . '/../src/*.php') ?: []);
};
