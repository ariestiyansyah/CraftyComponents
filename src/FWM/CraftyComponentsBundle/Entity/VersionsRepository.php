<?php

namespace FWM\CraftyComponentsBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Assetic\Asset\GlobAsset;
use Assetic\Filter\LessFilter;
use Assetic\Filter\Yui;
use FWM\ServicesBundle\Services\ArrayService;

/**
 * VerionsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class VersionsRepository extends EntityRepository
{
	public function createVersion($request, $componentData, $component, $versionType = false, $branch = 'master') {
        $em = $this->_em;

        /**
         * manage versionType name
         * 
         * If is additional branch add brnach name at end.
         */
        if ($branch != 'master' && $branch != false) {
            if($versionType) {
                $versionType = $versionType.'-'.$branch;
            } else {
                $versionType = $componentData['version']['value'].'-'.$branch;
            }
        } else {
            if($versionType) {
                $versionType = $versionType;
            } else {
                $versionType = $componentData['version']['value'];
            }
        }

        if(!$versionType) {
            $version = new \FWM\CraftyComponentsBundle\Entity\Versions();
            $version->setValue($versionType);
        } else {
            $componentData['version']['value'] = $versionType;
            $version = $em->getRepository('FWMCraftyComponentsBundle:Versions')->findOneBy(array(
                'component' => $component->getId(),
                'value' => $versionType
            ));

            if(!$version) {
                $version = new \FWM\CraftyComponentsBundle\Entity\Versions();
                $version->setValue($versionType);
            }
        }

        $version->setSha($componentData['version']['sha']);
        $version->setComponent($component);
        $version->setFileContent($componentData['componentFilesValue']);
        $version->setCreatedAt(new \DateTime());

        //complete all files 
        foreach(ArrayService::objectToArray(json_decode($componentData['componentFilesValue'])) as $value) {
            $tempFileContent[] = base64_decode($value);
        };
        $file = implode(' ', $tempFileContent);

        $path = $request->server->get('DOCUMENT_ROOT').'/uploads/components/'.strtolower($componentData['name']).'-'.strtolower($versionType);

        //create uncompressed version
        file_put_contents($path.'-uncompressed.js', $file);
        //minify uncompressed version
        try {
            $js = new AssetCollection(array(
                new FileAsset($path.'-uncompressed.js'),
            ), array(
                new Yui\JsCompressorFilter($request->server->get('DOCUMENT_ROOT').'/../app/Resources/java/yuicompressor.jar'),
            ));

            $minifidedFile = $js->dump();
        } catch(\Exception $e) {
            $minifidedFile = $file;
        };

        // create minified version
        file_put_contents($path.'.js', $minifidedFile);

        return $version;
    }
}