<?php
namespace Concrete\Package\CsvXmlConverter\Controller\SinglePage\Dashboard\System\CsvXmlConverter;

use Carbon\Carbon;
use Concrete\Core\Attribute\Category\AbstractCategory;
use Concrete\Core\Attribute\Category\PageCategory;
use Concrete\Core\Attribute\Key\CollectionKey;
use Concrete\Core\Attribute\Key\UserKey;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\File\File;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Utility\Service\Xml;
use League\Csv\Reader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Pages extends DashboardPageController
{
    public function select_mapping()
    {
        $header = [];
        if (!$this->token->validate('select_mapping')) {
            $this->error->add($this->token->getErrorMessage());
        }
        $fID = $this->request->request->get('csv');
        /** @var File|Version|\Concrete\Core\Entity\File\File $f */
        $f = File::getByID($fID);
        if (!is_object($f)) {
            $this->error->add(t('Invalid file.'));
        } else {
            ini_set("auto_detect_line_endings", true);

            $resource = $f->getFileResource();
            $reader = Reader::createFromStream($resource->readStream());
            $header = $reader->fetchOne();

            if (!is_array($header)) {
                $this->error->add(t('Invalid file.'));
            }
        }

        if (!$this->error->has()) {
            $this->set('f', $f);
            $this->set('header', $header);

            $options = [
                '' => t('Ignore'),
                'cName' => t('Page Name'),
                'cDatePublic' => t('Page Public Date'),
                'cPath' => t('Page Path'),
                'pType' => t('Page Type'),
                'pTemplate' => t('Page Template'),
                'cUser' => t('Page User'),
                'cDescription' => t('Page Description'),
                'cMain' => t('Page Content'),
            ];

            /** @var PageCategory $pageCategory */
            $pageCategory = $this->app->make(PageCategory::class);
            $keys = $pageCategory->getList();
            foreach ($keys as $key) {
                $options[$key->getAttributeKeyID()] = $key->getAttributeKeyDisplayName();
            }
            $this->set('options', $options);
        }
    }

    public function run_convert($fID = null)
    {
        if (!$this->token->validate('run_convert')) {
            $this->error->add($this->token->getErrorMessage());
        }

        /** @var File|Version|\Concrete\Core\Entity\File\File $f */
        $f = File::getByID($fID);
        if (!is_object($f)) {
            $this->error->add(t('Invalid file.'));
        } else {
            ini_set("auto_detect_line_endings", true);

            $resource = $f->getFileResource();
            $reader = Reader::createFromStream($resource->readStream());

            if (!is_object($reader)) {
                $this->error->add(t('Invalid file.'));
            }
        }

        if (!$this->error->has() && isset($reader)) {
            /** @var PageCategory $pageCategory */
            $pageCategory = $this->app->make(PageCategory::class);
            /** @var Xml $xml */
            $xml = $this->app->make('helper/xml');

            $mapping = $this->request->request->get('mapping');
            $reader->setOffset(1);
            $results = $reader->fetch();

            $root = new \SimpleXMLElement("<concrete5-cif></concrete5-cif>");
            $root->addAttribute('version', '1.0');
            $usersNode = $root->addChild('pages');

            foreach ($results as $result) {
                $cName = $cDatePublic = $cPath = $pType = $pTemplate = $cUser = $cDescription = $cMain = '';
                foreach ($mapping as $index => $key) {
                    if ($key == 'cName') {
                        $cName = $result[$index];
                    } elseif ($key == 'cDatePublic') {
                        $cDatePublic = (new \DateTime($result[$index]))->format('Y-m-d H:i:s');
                    } elseif ($key == 'cPath') {
                        $cPath = $result[$index];
                    } elseif ($key == 'pType') {
                        $pType = $result[$index];
                    } elseif ($key == 'pTemplate') {
                        $pTemplate = $result[$index];
                    } elseif ($key == 'cUser') {
                        $cUser = $result[$index];
                    } elseif ($key == 'cDescription') {
                        $cDescription = $result[$index];
                    } elseif ($key == 'cMain') {
                        $cMain = $result[$index];
                    }
                }

                $pageNode = $usersNode->addChild('page');
                $pageNode->addAttribute('name', $cName);
                $pageNode->addAttribute('public-date', $cDatePublic);
                $pageNode->addAttribute('path', $cPath);
                $pageNode->addAttribute('pagetype', $pType);
                $pageNode->addAttribute('template', $pTemplate);
                $pageNode->addAttribute('user', $cUser);
                $pageNode->addAttribute('description', $cDescription);

                $attributesNode = $pageNode->addChild('attributes');
                foreach ($mapping as $index => $akID) {
                    if (is_numeric($akID)) {
                        $ak = $pageCategory->getAttributeKeyByID($akID);
                        $akNode = $attributesNode->addChild('attributekey');
                        $akNode->addAttribute('handle', $ak->getAttributeKeyHandle());
                        $val = $result[$index];
                        switch ($ak->getAttributeTypeHandle()) {
                            case 'boolean':
                                $akNode->addChild('value', $val ? '1' : '0');
                                break;
                            case 'date_time':
                                $akNode->addChild('value', (new \DateTime($val))->format('Y-m-d H:i:s'));
                                break;
                            case 'image_file':
                                $akNode->addChild('value', '{ccm:export:file:' . $val . '}');
                                break;
                            case 'page_selector':
                                $akNode->addChild('value', $val);
                                break;
                            case 'select':
                                $options = explode(':', $val);
                                if ($options) {
                                    $av = $akNode->addChild('value');
                                    foreach ($options as $option) {
                                        $av->addChild('option', (string) $option);
                                    }
                                }
                                break;
                            case 'topics':
                                $topics = explode(':', $val);
                                if ($topics) {
                                    $av = $akNode->addChild('topics');
                                    foreach ($topics as $topic) {
                                        $xml->createCDataNode($av, 'topic', $topic);
                                    }
                                }
                                break;
                            default:
                                $xml->createCDataNode($akNode, 'value', $val);
                        }
                    }
                }

                if ($cMain) {
                    $areaNode = $pageNode->addChild('area');
                    $areaNode->addAttribute('name', 'Main');
                    $blocksNode = $areaNode->addChild('blocks');
                    $blockNode = $blocksNode->addChild('block');
                    $blockNode->addAttribute('type', 'content');
                    $dataNode = $blockNode->addChild('data');
                    $dataNode->addAttribute('table', 'btContentLocal');
                    $recordNode = $dataNode->addChild('record');
                    $xml->createCDataNode($recordNode, 'content', $cMain);
                }
            }

            $filename = 'export_pages_' . date('ymdhis') . '.xml';
            $response = new Response($root->asXML());
            $dispositionHeader = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
            $response->headers->set('Content-Disposition', $dispositionHeader);
            return $response;
        }
    }
}
