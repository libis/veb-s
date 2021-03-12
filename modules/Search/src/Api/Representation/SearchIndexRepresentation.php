<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2020
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Search\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class SearchIndexRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o:SearchIndex';
    }

    public function getJsonLd()
    {
        $entity = $this->resource;
        return [
            'o:name' => $entity->getName(),
            'o:adapter' => $entity->getAdapter(),
            'o:settings' => $entity->getSettings(),
            'o:created' => $this->getDateTime($entity->getCreated()),
        ];
    }

    public function adminUrl($action = null, $canonical = false)
    {
        $url = $this->getViewHelper('Url');
        $params = [
            'action' => $action,
            'id' => $this->id(),
        ];
        $options = [
            'force_canonical' => $canonical,
        ];

        return $url('admin/search/index-id', $params, $options);
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->resource->getName();
    }

    /**
     * @return string
     */
    public function cleanName()
    {
        return strtolower(str_replace('__', '_',
            preg_replace('/[^a-zA-Z0-9]/', '_', $this->resource->getName())
        ));
    }

    /**
     * Get unique short name of this index.
     *
     * The short name is used in Solr module to create a unique id, that should
     * be 32 letters max in order to be sorted (39rhjw-Z-item_sets/7654321:fr_FR),
     * it should be less than two letters, so don't create too much indexes.
     *
     * @return string
     */
    public function shortName()
    {
        return base_convert($this->id(), 10, 36);
    }

    /**
     * @return \Search\Adapter\AdapterInterface|null
     */
    public function adapter()
    {
        $name = $this->resource->getAdapter();
        $adapterManager = $this->getServiceLocator()->get('Search\AdapterManager');
        return $adapterManager->has($name)
            ? $adapterManager->get($name)
            : null;
    }

    /**
     * @return array
     */
    public function settings()
    {
        return $this->resource->getSettings();
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function setting($name, $default = null)
    {
        $settings = $this->resource->getSettings();
        return $settings[$name] ?? $default;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function settingAdapter($name, $default = null)
    {
        $settings = $this->resource->getSettings();
        return $settings['adapter'][$name] ?? $default;
    }

    /**
     * @return \DateTime
     */
    public function created()
    {
        return $this->resource->getCreated();
    }

    /**
     * @return \Search\Entity\SearchIndex
     */
    public function getEntity()
    {
        return $this->resource;
    }

    /**
     * @return string
     */
    public function adapterLabel()
    {
        $adapter = $this->adapter();
        if (!$adapter) {
            $translator = $this->getServiceLocator()->get('MvcTranslator');
            return sprintf($translator->translate('[Missing adapter "%s"]'), // @translate
                $this->resource->getAdapter()
            );
        }
        return $adapter->getLabel();
    }

    /**
     * @return \Search\Indexer\IndexerInterface NoopIndexer is returned when
     * the indexer is not available.
     */
    public function indexer()
    {
        $services = $this->getServiceLocator();
        $adapter = $this->adapter();
        if ($adapter) {
            $indexerClass = $adapter->getIndexerClass() ?: \Search\Indexer\NoopIndexer::class;
        } else {
            $indexerClass = \Search\Indexer\NoopIndexer::class;
        }

        /** @var \Search\Indexer\IndexerInterface $indexer */
        $indexer = new $indexerClass;
        return $indexer
            ->setServiceLocator($services)
            ->setSearchIndex($this)
            ->setLogger($services->get('Omeka\Logger'));
    }

    /**
     * @return \Search\Querier\QuerierInterface NoopQuerier is returned when
     * the querier is not available.
     */
    public function querier()
    {
        $services = $this->getServiceLocator();
        $adapter = $this->adapter();
        if ($adapter) {
            $querierClass = $adapter->getQuerierClass() ?: \Search\Querier\NoopQuerier::class;
        } else {
            $querierClass = \Search\Querier\NoopQuerier::class;
        }

        /** @var \Search\Querier\QuerierInterface $querier */
        $querier = new $querierClass;
        return $querier
            ->setServiceLocator($services)
            ->setSearchIndex($this)
            ->setLogger($services->get('Omeka\Logger'));
    }
}
