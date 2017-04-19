<?php
namespace Concrete\Package\CsvXmlConverter\Controller\SinglePage\Dashboard\System\CsvXmlConverter;

use Concrete\Core\Attribute\Category\AbstractCategory;
use Concrete\Core\Attribute\Key\UserKey;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\File\File;
use Concrete\Core\Http\Response;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\CsvXmlConverter\Attribute\Value\ImportValue;
use League\Csv\Reader;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Users extends DashboardPageController
{
    public function select_mapping()
    {
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
                'uName' => t('User Name'),
                'uEmail' => t('Email')
            ];

            /** @var \Concrete\Core\Entity\Attribute\Key\UserKey[] $keys */
            $keys = UserKey::getList();
            foreach ($keys as $key) {
                // @TODO: support not string attribute type
                if (!in_array($key->getAttributeTypeHandle(), [
                    'address', 'boolean', 'image_file', 'select', 'social_links', 'topics'
                ])) {
                    $options[$key->getAttributeKeyID()] = $key->getAttributeKeyDisplayName();
                }
            }
            $this->set('options', $options);
        } else {
            $this->view();
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

        if (!$this->error->has()) {
            $mapping = $this->request->request->get('mapping');
            $reader->setOffset(1);
            $results = $reader->fetch();

            $root = new \SimpleXMLElement("<concrete5-cif></concrete5-cif>");
            $root->addAttribute('version', '1.0');
            $usersNode = $root->addChild('users');

            foreach ($results as $result) {
                $uName = '';
                $uEmail = '';
                foreach ($mapping as $index => $akID) {
                    if ($akID == 'uName') {
                        $uName = $result[$index];
                    } elseif ($akID == 'uEmail') {
                        $uEmail = $result[$index];
                    }
                }

                if (!$uEmail) {
                    throw new \Exception(t('Email field is required.'));
                }

                if (!$uName) {
                    $uName = str_replace('@', '.', $uEmail);
                }

                $userNode = $usersNode->addChild('user');
                $userNode->addAttribute('username', $uName);
                $userNode->addAttribute('email', $uEmail);

                $attributesNode = $userNode->addChild('attributes');
                foreach ($mapping as $index => $akID) {
                    if (is_numeric($akID)) {
                        /** @var AbstractCategory $ak */
                        $ak = UserKey::getByID($akID);
                        $akNode = $attributesNode->addChild('attributekey');
                        $akNode->addAttribute('handle', $ak->getAttributeKeyHandle());
                        $cnode = $akNode->addChild('value');
                        $node = dom_import_simplexml($cnode);
                        $no = $node->ownerDocument;
                        $node->appendChild($no->createCDataSection($result[$index]));
                    }
                }
            }

            $filename = 'export_users_' . date('ymdhis') . '.xml';
            $response = new Response($root->asXML());
            $dispositionHeader = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
            $response->headers->set('Content-Disposition', $dispositionHeader);
            return $response;
        } else {
            $this->view();
        }
    }
}
