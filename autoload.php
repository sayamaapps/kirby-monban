<?php

/**
 * Class map for Kirby's F::loadClasses(). Keys are lowercase class names,
 * values are paths relative to the plugin root. tests/AutoloadTest.php checks the
 * map against src/.
 */

return [
	'sayamaapps\\monban\\guard' => 'src/Guard.php',
	'sayamaapps\\monban\\options' => 'src/Options.php',
	'sayamaapps\\monban\\puzzle' => 'src/Puzzle.php',
];
