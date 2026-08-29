<?php

/**
 * Minimal stand-ins for the WHMCS core classes GetTldPricing() depends on
 * (`use WHMCS\Domain\TopLevel\ImportItem`, `WHMCS\Results\ResultsList`,
 * `WHMCS\Database\Capsule`). None of this ships in the release package — see
 * .github/workflows/release.yml, which only zips
 * modules/registrars/domain_reseller_registrar/.
 */

namespace WHMCS\Domain\TopLevel;

class ImportItem
{
    public $extension;
    public $minYears;
    public $maxYears;
    public $registerPrice;
    public $renewPrice;
    public $transferPrice;
    public $currency;
    public $years;
    public $eppRequired;

    public function setExtension($v) { $this->extension = $v; return $this; }
    public function setMinYears($v) { $this->minYears = $v; return $this; }
    public function setMaxYears($v) { $this->maxYears = $v; return $this; }
    public function setRegisterPrice($v) { $this->registerPrice = $v; return $this; }
    public function setRenewPrice($v) { $this->renewPrice = $v; return $this; }
    public function setTransferPrice($v) { $this->transferPrice = $v; return $this; }
    public function setCurrency($v) { $this->currency = $v; return $this; }
    public function setYears($v) { $this->years = $v; return $this; }
    public function setEppRequired($v) { $this->eppRequired = $v; return $this; }
}

namespace WHMCS\Results;

class ResultsList extends \ArrayObject
{
}

namespace WHMCS\Database;

class Capsule
{
    /** @var object|null Set by a test before calling GetTldPricing(). */
    public static $currencyRow = null;

    public static function table($name)
    {
        return new class {
            public function where($col, $val) { return $this; }
            public function first() { return Capsule::$currencyRow; }
        };
    }
}
