<?php
namespace Concrete\Package\CsvXmlConverter;

use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Package\Package;

class Controller extends Package
{
    /**
     * @var string Package handle.
     */
    protected $pkgHandle = 'csv_xml_converter';

    /**
     * @var string Required concrete5 version.
     */
    protected $appVersionRequired = '8.2.0a2';

    /**
     * @var string Package version.
     */
    protected $pkgVersion = '0.2';
    
    /**
     * @var array Array of location -> namespace autoloader entries for the package.
     */
    protected $pkgAutoloaderRegistries = [];
    
    protected $pkgAutoloaderMapCoreExtensions = true;
    
    /**
     * Returns the translated name of the package.
     *
     * @return string
     */
    public function getPackageName()
    {
        return t('CSV XML Converter');
    }

    /**
     * Returns the translated package description.
     *
     * @return string
     */
    public function getPackageDescription()
    {
        return t('Generate Import Batch XML from CSV file.');
    }

    /**
     * Installs the package info row and installs the database. Packages installing additional content should override this method, call the parent method,
     * and use the resulting package object for further installs.
     *
     * @return Package
     */
    public function install()
    {
        $pkg = parent::install();
        $this->installContentFile('/config/dashboard.xml');

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installContentFile('/config/dashboard.xml');
    }
}
