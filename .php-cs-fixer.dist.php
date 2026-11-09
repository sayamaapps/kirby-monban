<?php

// The Kirby style removes @package from class headers. The Kirby core keeps them,
// and so does this plugin: the rule is switched off here and nowhere else.
return Kirby\PhpCs\Config::create()
	->setRules([
		'@Kirby/style:risky'  => true,
		'phpdoc_no_package'   => false,
	])
	->setFinder(
		PhpCsFixer\Finder::create()
			->exclude('vendor')
			->exclude('node_modules')
			->in(__DIR__)
	);
