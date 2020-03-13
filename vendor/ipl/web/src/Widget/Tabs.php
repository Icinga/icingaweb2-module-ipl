<?php

namespace ipl\Web\Widget;

use Exception;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\MenuAction;
use Icinga\Web\Widget\Tabextension\OutputFormat;
use InvalidArgumentException;
use ipl\Html\ValidHtml;

/**
 * @TODO(el): Don't depend on Icinga Web's Tabs
 */
class Tabs extends \Icinga\Web\Widget\Tabs implements ValidHtml
{
    /** @var bool Whether data exports are enabled */
    protected $dataExportsEnabled = false;

    /** @var bool Whether the legacy extensions should be shown by default */
    protected $legacyExtensionsEnabled = true;

    /**
     * Don't show legacy extensions by default
     */
    public function disableLegacyExtensions()
    {
        $this->legacyExtensionsEnabled = false;
    }

    /**
     * Show export actions for JSON and CSV
     */
    public function enableDataExports()
    {
        $this->dataExportsEnabled = true;
    }

    /**
     * Activate the tab with the given name
     *
     * @param string $name
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function activate($name)
    {
        try {
            parent::activate($name);
        } catch (Exception $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        return $this;
    }

    /**
     * Add the given tab
     *
     * @param string $name
     * @param mixed  $tab
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function add($name, $tab)
    {
        try {
            parent::add($name, $tab);
        } catch (Exception $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        return $this;
    }

    public function render()
    {
        if ($this->legacyExtensionsEnabled) {
            $this->extend(new OutputFormat(
                $this->dataExportsEnabled
                    ? []
                    : [OutputFormat::TYPE_CSV, OutputFormat::TYPE_JSON]
            ))
                ->extend(new DashboardAction())
                ->extend(new MenuAction());
        }

        return parent::render();
    }
}
