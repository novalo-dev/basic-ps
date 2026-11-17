<?php

// The PrestaShop module coding standard, as PrestaShop's own modules apply it.
// The licence header sits right under <?php, as in PrestaShop's own modules:
// php-dev-tools turned blank_line_after_opening_tag off after its v5 release,
// and its Config returns a fixed rule list, so it is turned off here until the
// next release ships that.
$config = new class extends PrestaShop\CodingStandards\CsFixer\Config {
    public function getRules(): array
    {
        return array_merge(parent::getRules(), ['blank_line_after_opening_tag' => false]);
    }
};

/** @var Symfony\Component\Finder\Finder $finder */
$finder = $config->setUsingCache(false)->getFinder();
$finder->in(__DIR__)->exclude(['vendor', 'dev', 'views']);

return $config;
