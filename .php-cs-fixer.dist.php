<?php

// The PrestaShop module coding standard, as PrestaShop's own modules apply it.
$config = new PrestaShop\CodingStandards\CsFixer\Config();

/** @var Symfony\Component\Finder\Finder $finder */
$finder = $config->setUsingCache(false)->getFinder();
$finder->in(__DIR__)->exclude(['vendor', 'dev', 'views']);

return $config;
