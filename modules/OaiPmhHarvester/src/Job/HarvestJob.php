<?php
namespace OaiPmhHarvester\Job;

use Omeka\Job\AbstractJob;
use SimpleXMLElement;

class HarvestJob extends AbstractJob
{
    /*Xml schema and OAI prefix for the format represented by this class
     * These constants are required for all maps
     */
    /** OAI-PMH metadata prefix */
    const METADATA_PREFIX = 'mets';

    /** XML namespace for output format */
    const METS_NAMESPACE = 'http://www.loc.gov/METS/';

    /** XML schema for output format */
    const METADATA_SCHEMA = 'http://www.loc.gov/standards/mets/mets.xsd';

    /** XML namespace for unqualified Dublin Core */
    const DUBLIN_CORE_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    const DCTERMS_NAMESPACE = 'http://purl.org/dc/terms/';

    const OAI_DC_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/oai_dc/';
    const OAI_DCTERMS_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/oai_dcterms/';

    const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    const OAI_DC_SCHEMA = 'http://www.openarchives.org/OAI/2.0/oai_dc/';

    const MMFC_NAMESPACE = 'http://heron-net.be/ca_cct/mmfc.xsd';

    protected $api;

    protected $logger;

    protected $hasErr = false;

    protected $resource_type;

    protected $dcProperties;

    public function perform()
    {
        $this->logger = $this->getServiceLocator()->get('Omeka\Logger');
        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');

        // Set Dc Properties for mapping
        $dcProperties = $this->api->search('properties', ['vocabulary_id' => 5], ['responseContent' => 'resource'])->getContent();


        $elements = [];
        foreach ($dcProperties as $property) {
            $elements[$property->getId()] = $property->getLocalName();
        }
        $this->dcProperties = $elements;

        $args = $this->job->getArgs();

        if($args['metadata_prefix'] == 'oai_veb'):
          $dcProperties = $this->api->search('properties', ['vocabulary_id' => 5], ['responseContent' => 'resource'])->getContent();
        endif;

        $harvestJson = [
            'o:job' => ['o:id' => $this->job->getId()],
            'comment' => 'Harvesting started',
            'has_err' => 0,
            'base_url' => $args['base_url'],
            'set_name' => $args['set_name'],
            'set_spec' => $args['set_spec'],
            'collection_id' => $args['collection_id'],
            'metadata_prefix' => $args['metadata_prefix'],
            'resource_type' => $this->getArg('resource_type', 'items'),
            'resource_template' => $args['resource_template']
        ];

        // TODO : autres protocoles.
        $method = '';
        switch ($args['metadata_prefix']) {
            case 'mets':
                $method = '_dmdSecToJson';
                break;
            case 'oai_dc':
            case 'dc':
                $method = '_oaidcToJson';
                break;
            case 'oai_veb':
                $method = '_mmfcToJson';
                break;
            case 'dcterms':
            case 'oai_dcterms':
                $method = '_oaidctermsToJson';
                break;
            default:
                // TODO : Exception ou message d'erreur
        }

        $resumptionToken = false;
        do {
            if ($resumptionToken) {
                $url = $args['base_url'] . "?resumptionToken=$resumptionToken&verb=ListRecords";
            } else {
                $url = $args['base_url'] . "?metadataPrefix=" . $args['metadata_prefix'] . "&verb=ListRecords&set=" . $args['set_spec'];
            }

            $response = \simplexml_load_file($url);

            $records = $response->ListRecords;

            //$toInsert = [];
            $toInsert = [];$ids= []; $update_id='';$icount = 0;$ucount = 0;
            foreach ($records->record as $record) {
                //$toInsert[] = $this->{$method}($record, $args['collection_id'],$args);

                $pre_record = $this->{$method}($record, $args['collection_id'],$args);
                $id_exists = $this->itemExists($pre_record, $pre_record['mmfc:idno'][0]['@value'],$args['resource_type'],$args['resource_template']);

                if(!$id_exists && $pre_record['mmfc:idno'][0]['@value']){
                  try{
                      $response_c = $this->api->create($args['resource_type'], $pre_record, [], []);
                      $response_c = null;
                    }catch(\Throwable $t){
                      $this->logger->info($pre_record['mmfc:idno'][0]['@value']." error");
                    }
                }
            }

            //$this->createItems($toInsert);

            if (isset($response->ListRecords->resumptionToken) && $response->ListRecords->resumptionToken <> '') {
                $resumptionToken = $response->ListRecords->resumptionToken;
            } else {
                $resumptionToken = false;
            }
        } while ($resumptionToken);

        $response = $this->api->create('oaipmhharvester_harvestjob', $harvestJson);
        $importRecordId = $response->getContent()->id();

        // Update du job
        $comment = $this->getArg('comment');
        $harvestJson = [
            'comment' => $comment,
            'has_err' => $this->hasErr,
            // TODO Nombre d'items ?
            'nb_items' => count($sets),
        ];

        $response = $this->api->update('oaipmhharvester_harvestjob', $importRecordId, $harvestJson);
    }

    protected function createItems($toCreate)
    {
        $insertJson = [];
        foreach ($toCreate as $index => $item) {
            $insertJson[] = $item;
            if ($index % 20 == 0) {
                $createResponse = $this->api->batchCreate('items', $insertJson, [], ['continueOnError' => true]);
                $this->createRollback($createResponse->getContent());
                $insertJson = [];
            }
        }

        $createResponse = $this->api->batchCreate('items', $insertJson, [], ['continueOnError' => true]);

        $this->createRollback($createResponse->getContent());

        $createImportEntitiesJson = [];
    }

    protected function itemExists($item, $id_version, $resource_type,$template){
        //assuming dc:isVersionOf as unique accross all items

        $query = [];
        $query['property'][0] = array(
          'property' => 185,
          'text' => $id_version,
          'type' => 'eq',
          'joiner' => 'and'
        );

        $query['resource_template_id'][] = $template;

        $results = '';
        $response = $this->api->search($resource_type,$query);
        $results = $response->getContent();

        foreach($results as $result):
          if($result):
            try{
              //don't update files for to avoid redownload
              if(isset($item['o:media'])):
                unset($item['o:media']);
              endif;
              $response = $this->api->update($resource_type, $result->id() ,$item, [], ['isPartial' => true, 'flushEntityManager' => true]);
              $response = null;
            }catch(\Throwable $t){
              $this->logger->info($item['mmfc:idno'][0]['@value']." error");
            }
            return true;
          endif;
        endforeach;

        return false;
    }

    protected function collectionExists($id){
        //find matching identifier

        $query = [];
        $query['property'][0] = array(
          'property' => 10,
          'text' => $id,
          'type' => 'eq',
          'joiner' => 'and'
        );

        $results = '';
        $response = $this->api->search('item_sets',$query);
        $results = $response->getContent();

        foreach($results as $result):
          if($result):
            return $result->id();
          endif;
        endforeach;

        return false;
    }

    protected function createRollback($records)
    {
        foreach ($records as $resourceReference) {
            $createImportEntitiesJson[] = $this->buildImportRecordJson($resourceReference);
        }
        $createImportRecordResponse = $this->api->batchCreate('oaipmhharvester_entities', $createImportEntitiesJson, [], ['continueOnError' => true]);
        return $createImportRecordResponse;
    }

    /**
     * Convenience function that returns the
     * xmls dmdSec as an Omeka ElementTexts array
     *
     * @param SimpleXMLElement $record
     * @return boolean/array
     */
    private function _dmdSecToJson(SimpleXMLElement $record, $setId)
    {
        $mets = $record->metadata->mets->children(self::METS_NAMESPACE);
        $meta = null;
        foreach ($mets->dmdSec as $k) {
            $dcMetadata = $k
                ->mdWrap
                ->xmlData
                ->children(self::DUBLIN_CORE_NAMESPACE);

            $elementTexts = [];
            foreach ($this->dcProperties as $propertyId => $localName) {
                if (isset($dcMetadata->$localName)) {
                    $elementTexts["dcterms:$localName"] = $this->extractValues($dcMetadata, $propertyId);
                }
            }
            $meta = $elementTexts;
            $meta['o:item_set'] = ["o:id" => $setId];
        }
        return $meta;
    }

    private function _oaidcToJson(SimpleXMLElement $record, $setId)
    {
        $dcMetadata = $record
            ->metadata
            ->children(self::OAI_DC_NAMESPACE)
            ->children(self::DUBLIN_CORE_NAMESPACE);

        $elementTexts = [];
        foreach ($this->dcProperties as $propertyId => $localName) {
            if (isset($dcMetadata->$localName)) {
                $elementTexts["dcterms:$localName"] = $this->extractValues($dcMetadata, $propertyId);
            }
        }

        $meta = $elementTexts;
        $meta['o:item_set'] = ["o:id" => $setId];
        return $meta;
    }

    private function _oaidctermsToJson(SimpleXMLElement $record, $setId)
    {
        $dcMetadata = $record
            ->metadata
            ->children(self::OAI_DCTERMS_NAMESPACE)
            ->children(self::DCTERMS_NAMESPACE);

        $elementTexts = [];
        foreach ($this->dcProperties as $propertyId => $localName) {
            if (isset($dcMetadata->$localName)) {
                $elementTexts["dcterms:$localName"] = $this->extractValues($dcMetadata, $propertyId);
            }
        }
        $meta = $elementTexts;
        $meta['o:item_set'] = ["o:id" => $setId];
        return $meta;
    }

    private function _mmfcToJson(SimpleXMLElement $record, $setId,$args)
    {
        $this->logger->info("1");
        $dcMetadata = $record
            ->metadata
            ->children('')
            ->children('mmfc',true);

        $elementTexts = [];
        foreach ($this->dcProperties as $propertyId => $localName) {
            $this->logger->info($localName);
            if (isset($dcMetadata->$localName)) {
                $this->logger->info("3");
                $elementTexts["mmfc:$localName"] = $this->extractValues($dcMetadata, $propertyId);
            }
        }
        $meta = $elementTexts;
        if(isset($args['resource_template'])):
              $meta['o:resource_template'] = ["o:id" => $args['resource_template']];
        endif;
        //$meta['o:item_set'] = ["o:id" => $setId];
        return $meta;
    }

    protected function extractValues(SimpleXMLElement $metadata, $propertyId)
    {
        $data = [];
        $localName = $this->dcProperties[$propertyId];
        foreach ($metadata->$localName as $value) {
            $texts = trim($value);
            $texts = explode('||',$texts);
            foreach($texts as $text):
              if (!mb_strlen($text)) {
                  continue;
              }

              // Extract xsi type if any.
              $attributes = iterator_to_array($value->attributes('xsi', true));
              $type = empty($attributes['type']) ? null : trim($attributes['type']);
              $type = in_array(strtolower($type), ['dcterms:uri', 'uri']) ? 'uri' : 'literal';

              $val = [
                  'property_id' => $propertyId,
                  'type' => $type,
                  // "value_is_html" => false,
                  'is_public' => true,
              ];

              switch ($type) {
                  case 'uri':
                      $val['o:label'] = null;
                      $val['@id'] = $text;
                      break;

                  case 'literal':
                  default:
                      // Extract xml language if any.
                      $attributes = iterator_to_array($value->attributes('xml', true));
                      $language = empty($attributes['lang']) ? null : trim($attributes['lang']);

                      $val['@value'] = $text;
                      $val['@language'] = $language;
                      break;
              }

              $data[] = $val;
            endforeach;
        }
        return $data;
    }

    protected function buildImportRecordJson($resourceReference)
    {
        $recordJson = ['o:job' => ['o:id' => $this->job->getId()],
            'entity_id' => $resourceReference->id(),
            'resource_type' => $this->getArg('entity_type', 'items'),
        ];
        return $recordJson;
    }
}
