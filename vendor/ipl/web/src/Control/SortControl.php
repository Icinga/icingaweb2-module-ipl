<?php

namespace ipl\Web\Control;

use ipl\Html\FormElement\ButtonElement;
use ipl\Stdlib\Str;
use ipl\Web\Compat\CompatForm;
use ipl\Web\Url;
use ipl\Web\Widget\Icon;

/**
 * Allows to adjust the order of the items to display
 */
class SortControl extends CompatForm
{
    /** @var string Default sort param */
    const DEFAULT_SORT_PARAM = 'sort';

    protected $defaultAttributes = ['class' => 'icinga-form inline sort-control'];

    /** @var string Name of the URL parameter which stores the sort column */
    protected $sortParam = self::DEFAULT_SORT_PARAM;

    /** @var Url Request URL */
    protected $url;

    /** @var array Possible sort columns as sort string-value pairs */
    private $columns;

    /** @var string Default sort string */
    private $default;

    protected $method = 'GET';

    /**
     * Create a new sort control
     *
     * @param Url $url Request URL
     */
    public function __construct(Url $url)
    {
        $this->url = $url;
    }

    /**
     * Get the possible sort columns
     *
     * @return array Sort string-value pairs
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Set the possible sort columns
     *
     * @param array $columns Sort string-value pairs
     *
     * @return $this
     */
    public function setColumns(array $columns)
    {
        // We're working with lowercase keys throughout the sort control
        $this->columns = array_change_key_case($columns, CASE_LOWER);

        return $this;
    }

    /**
     * Get the default sort string
     *
     * @return string
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Set the default sort string
     *
     * @param array|string $default
     *
     * @return $this
     */
    public function setDefault($default)
    {
        // We're working with lowercase keys throughout the sort control
        $this->default = strtolower($default);

        return $this;
    }

    /**
     * Get the name of the URL parameter which stores the sort
     *
     * @return string
     */
    public function getSortParam()
    {
        return $this->sortParam;
    }

    /**
     * Set the name of the URL parameter which stores the sort
     *
     * @param string $sortParam
     *
     * @return $this
     */
    public function setSortParam($sortParam)
    {
        $this->sortParam = $sortParam;

        return $this;
    }

    /**
     * Get the sort string
     *
     * @return string|null
     */
    public function getSort()
    {
        $sort = $this->url->getParam($this->getSortParam(), $this->getDefault());

        if (! empty($sort)) {
            $columns = $this->getColumns();

            if (! isset($columns[$sort])) {
                // Choose sort string based on the first closest match
                foreach (array_keys($columns) as $key) {
                    if (Str::startsWith($key, $sort)) {
                        $sort = $key;

                        break;
                    }
                }
            }
        }

        return $sort;
    }

    protected function assemble()
    {
        $columns = $this->getColumns();
        $sort = $this->getSort();

        if (empty($sort)) {
            reset($columns);
            $sort = key($columns);
        }

        $sort = explode(',', $sort, 2);
        list($column, $direction) = Str::symmetricSplit(array_shift($sort), ' ', 2);

        $toggle = ['asc' => 'sort-name-down', 'desc' => 'sort-name-up'];
        unset($toggle[strtolower($direction) ?: 'asc']);
        $toggleIcon = reset($toggle);
        $toggleDirection = key($toggle);

        if ($direction !== null) {
            $value = implode(',', array_merge(["{$column} {$direction}"], $sort));
            if (! isset($columns[$value])) {
                foreach ([$column, "{$column} {$toggleDirection}"] as $key) {
                    $key = implode(',', array_merge([$key], $sort));
                    if (isset($columns[$key])) {
                        $columns[$value] = $columns[$key];
                        unset($columns[$key]);

                        break;
                    }
                }
            }
        } else {
            $value = implode(',', array_merge([$column], $sort));
        }

        if (! isset($columns[$value])) {
            $columns[$value] = 'Custom';
        }

        $this->addElement('select', $this->getSortParam(), [
            'class'   => 'autosubmit',
            'label'   => 'Sort By',
            'options' => $columns,
            'value'   => $value
        ])
            ->getElement($this->getSortParam())
            ->getWrapper()
            ->getAttributes()
            ->add('class', 'icinga-controls');

        $toggleButton = new ButtonElement($this->getSortParam(), [
            'class' => 'link-button spinner',
            'type'  => 'submit',
            'value' => implode(',', array_merge(["{$column} {$toggleDirection}"], $sort))
        ]);
        $toggleButton->add(new Icon($toggleIcon));

        $this->addElement($toggleButton);
    }
}
