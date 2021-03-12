<?php declare(strict_types=1);

namespace Search\Indexer;

use Laminas\Log\LoggerAwareTrait;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Entity\Resource;
use Search\Api\Representation\SearchIndexRepresentation;
use Search\Query;

class NoopIndexer implements IndexerInterface
{
    use LoggerAwareTrait;

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        return $this;
    }

    public function setSearchIndex(SearchIndexRepresentation $index)
    {
        return $this;
    }

    public function canIndex($resourceName)
    {
        return false;
    }

    public function clearIndex(Query $query = null)
    {
        return $this;
    }

    public function indexResource(Resource $resource)
    {
        return $this;
    }

    public function indexResources(array $resources)
    {
        return $this;
    }

    public function deleteResource($resourceName, $id)
    {
        return $this;
    }
}
