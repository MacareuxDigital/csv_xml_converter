<?php

namespace Concrete\Package\CsvXmlConverter\Controller\SinglePage\Dashboard\System;

use Concrete\Core\Page\Controller\DashboardPageController;

class CsvXmlConverter extends DashboardPageController
{
    public function view()
    {
        $this->redirect('/dashboard/system/csv_xml_converter/users');
    }
}
