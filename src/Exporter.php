<?php
/**
 * GridExporterDisplayer
 *
 * @author zhusaidong <zhusaidong@gmail.com>
 */

namespace Zhusaidong\GridExporter;

use Encore\Admin\Grid;
use Encore\Admin\Grid\Column;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Exception;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Encore\Admin\Grid\Exporters\AbstractExporter;
use Encore\Admin\Grid\Row;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class Exporter extends AbstractExporter implements FromCollection, WithHeadings, ShouldAutoSize, WithCustomValueBinder, WithEvents
{
    use Exportable;

    /**
     * @var array $columns
     */
    private $columns = [];
    /**
     * @var string $fileName 文件名
     */
    private $fileName = 'Exporter.xlsx';
    /**
     * @var array $exclusions 排除项
     */
    private $exclusions = [];
    /**
     * @var array $registerEvents
     */
    private $registerEvents = [];

    protected $htmlEntities = [
        '&nbsp;', '&lt', '&gt;', '&amp;', '&quot;', '&cent;', '&pound;', '&yen;', '&euro;', '&sect;', '&copy;', '&reg;', '&trade;', '&times;', '&divide;'
    ];

    /**
     * @inheritDoc
     */
    public function headings(): array
    {
        if (!empty($this->columns)) {
            return $this->columns;
        }

        $this->exclusions = collect($this->exclusions)->map(static function ($exclusion) {
            return Str::snake($exclusion);
        });

        $this->columns = $this->grid->visibleColumns()->mapWithKeys(static function (Column $column) {
            return [$column->getName() => $column->getLabel()];
        })->except($this->exclusions);

        return $this->columns->toArray();
    }

    /**
     * @inheritDoc
     */
    public function collection()
    {
        $lists = [];
        $this->chunk(function ($collection) use (&$lists) {
            Column::setOriginalGridModels($collection);
            $original = $current = $collection->toArray();
            $this->grid->getColumns()->map(function (Column $column) use (&$current) {
                $current = $column->fill($current);
            });
            foreach ($current as $row) {
                $data = [];
                foreach ($this->columns as $key => $column) {
                    if (strstr($key, '.')) {
                        $name = explode('.', $key);
                        if (count($name) === 2) {
                            $data[$column] = self::strFilter($this->htmlEntities, $row[$name[0]][$name[1]]);
                            continue;
                        }
                        if (count($name) === 3) {
                            $data[$column] = self::strFilter($this->htmlEntities, $row[$name[0]][$name[1]][$name[2]]);
                            continue;
                        }
                        $data[$column] = '';
                        continue;
                    };
                    $data[$column] = self::strFilter($this->htmlEntities, $row[$key]);
                }
                $lists[] = $data;
            }
        });
        return new Collection($lists);
    }

    /**
     * {@inheritdoc}
     */
    public function export()
    {
        $this->download($this->fileName)->prepare(request())->send();

        exit;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function bindValue(Cell $cell, $value)
    {
        $cell->setValueExplicit($value, DataType::TYPE_STRING);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function registerEvents(): array
    {
        return $this->registerEvents;
    }

    /**
     * 设置文件名
     *
     * @param string $fileName
     *
     * @return Exporter
     */
    public function setFileName(string $fileName): Exporter
    {
        if (empty(pathinfo($fileName, PATHINFO_EXTENSION))) {
            $fileName .= '.xlsx';
        }
        $this->fileName = $fileName;

        return $this;
    }

    /**
     * 排除项
     *
     * @param array $exclusions
     *
     * @return Exporter
     */
    public function setExclusions(array $exclusions): Exporter
    {
        $this->exclusions = array_merge_recursive($this->exclusions, $exclusions);

        return $this;
    }

    /**
     * 排除项
     *
     * @param string $exclusion
     *
     * @return Exporter
     */
    public function setExclusion(string $exclusion): Exporter
    {
        $this->exclusions[] = $exclusion;

        return $this;
    }

    /**
     * @param array $registerEvents
     */
    public function setRegisterEvents(array $registerEvents): void
    {
        $this->registerEvents = $registerEvents;
    }

    /**
     * 获取 Grid exporter
     *
     * @param Grid $grid
     *
     * @return NULL|Exporter
     */
    public static function get(Grid $grid): ?Exporter
    {
        return (function () {
            return $this->exporter instanceof Exporter ? $this->exporter : null;
        })->call($grid);
    }

    /**
     * 过滤函数
     * @param array $filter
     * @param string|null $str
     * @return string
     */
    protected static function strFilter(array $filter, string|null $str): string
    {
        if (empty($str)) {
            return '';
        }
        $result =  trim(str_replace($filter, '', strip_tags(preg_replace(/** @lang text */ '/<script(.*)>(.*)<\/script>|<template(.*)>(.*)<\/template>/iUs', '', $str))));
        return html_entity_decode($result);
    }
}
