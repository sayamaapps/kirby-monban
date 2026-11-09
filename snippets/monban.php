<?php
/**
 * The protection of one form: the decoy field, the time stamp, the
 * proof-of-work widget and, once per page, the stylesheet and script tags.
 * Print it inside the <form>. Prints nothing while `sayamaapps.monban.active`
 * is false.
 *
 *   <form method="post" action="…">
 *     <?php snippet('monban') ?>
 *     …
 *   </form>
 */
?>
<?= \Sayamaapps\Monban\Guard::fields(\Kirby\Cms\App::instance()) ?>
