<?php

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 *
 * PHP version 7
 *
 * @category  Inventory
 * @package   Glpi
 * @author    Johan Cwiklinski <jcwiklinski@teclib.com>
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://glpi-project.org
 */

namespace Glpi\Inventory;

use DateTime;
use Exception;
use RuntimeException;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\Schema;
use UnexpectedValueException;

/**
 * Converts old FusionInventory XML format to new JSON schema
 * for automatic inventory.
 *
 * @category  Inventory
 * @package   Glpi
 * @author    Johan Cwiklinski <jcwiklinski@teclib.com>
 * @copyright 2018-2022 GLPI Team and Contributors
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      https://glpi-project.org
 */
class Converter
{
    public const LAST_VERSION = 0.1;

    /** @var ?float */
    private ?float $target_version;

    /** @var bool */
    private bool $debug = false;
    /**
     * XML a different steps. Used for debug only
     * @var array<int, mixed>
     */
    private array $steps;

    /** @var array<string, float> */
    private array $mapping = [
        '01'   => 0.1
    ];

    /** @var array<string, array<int, string>> */
    private array $schema_patterns;
    /** @var array<string, array<string, string>> */
    private array $extra_properties = [];
    /** @var array<string, array<string, array<string, string>>> */
    private array $extra_sub_properties = [];

    /**
     * @var array<string, array<int, string>>
     *
     * A two dimensions array with types as key,
     * and nodes names as values.
     *
     * Node name is expected to be defined with its parent
     * separated with a "/":
     * $convert_types = [
     *     'integer' => [
     *         'drives/free'
     *     ]
     * ];
     *
     * With above example, 'drives/free' will replace all
     * entries found as $drives['free'] and $drives[$i]['free']
     * with their value cast to integer.
     *
     * @see Converter::getCastedValue() for supported types
     * @see Converter::convertTypes() for usage
     */
    private array $convert_types;

    /**
     * Instantiate converter
     *
     * @param ?float $target_version JSON schema based version to target. Use last version if null.
     */
    public function __construct($target_version = null)
    {
        if ($target_version === null) {
            $target_version = self::LAST_VERSION;
        }

        if (!is_double($target_version)) {
            throw new UnexpectedValueException('Version must be a double!');
        }

        $this->target_version = $target_version;
    }

    /**
     * Get target version
     *
     * @return float
     */
    public function getTargetVersion(): float
    {
        return $this->target_version ?? self::LAST_VERSION;
    }

    /**
     * Set debug on/off
     *
     * @param boolean $debug Debug active or not
     *
     * @return Converter
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Is debug mode on?
     *
     * @return boolean
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Get path to schema
     *
     * @return string
     */
    public function getSchemaPath(): string
    {
        $schema_path = realpath(__DIR__ . '/../../inventory.schema.json');
        if ($schema_path === false) {
            throw new RuntimeException('Schema file not found!');
        }
        return $schema_path;
    }

    /**
     * @param array<string, array<string, string>> $properties
     * @return $this
     */
    public function setExtraProperties(array $properties): self
    {
        $this->extra_properties = $properties;
        return $this;
    }

    /**
     * @param array<string, array<string, array<string, string>>> $properties
     * @return $this
     */
    public function setExtraSubProperties(array $properties): self
    {
        $this->extra_sub_properties = $properties;
        return $this;
    }

    /**
     * Build (extended) JSON schema
     * @return mixed
     */
    public function buildSchema()
    {
        $string = file_get_contents($this->getSchemaPath());
        if ($string === false) {
            throw new RuntimeException('Unable to read schema file');
        }
        $schema = json_decode($string);

        $properties = $schema->properties->content->properties;

        if ($this->extra_properties != null) {
            foreach ($this->extra_properties as $extra_property => $extra_config) {
                if (!property_exists($properties, $extra_property)) {
                    $properties->$extra_property = json_decode((string)json_encode($extra_config));
                } else {
                    trigger_error(
                        sprintf('Property %1$s already exists in schema.', $extra_property),
                        E_USER_WARNING
                    );
                }
            }
        }

        if ($this->extra_sub_properties != null) {
            foreach ($this->extra_sub_properties as $extra_sub_property => $extra_sub_config) {
                if (property_exists($properties, $extra_sub_property)) {
                    foreach ($extra_sub_config as $subprop => $subconfig) {
                        $type = $properties->$extra_sub_property->type;
                        switch ($type) {
                            case 'array':
                                if (!property_exists($properties->$extra_sub_property->items->properties, $subprop)) {
                                    $properties->$extra_sub_property->items->properties->$subprop =
                                        json_decode((string)json_encode($subconfig));
                                } else {
                                    trigger_error(
                                        sprintf('Property %1$s already exists in schema.', $subprop),
                                        E_USER_WARNING
                                    );
                                }
                                break;
                            case 'object':
                                if (!property_exists($properties->$extra_sub_property->properties, $subprop)) {
                                    $properties->$extra_sub_property->properties->$subprop =
                                        json_decode((string)json_encode($subconfig));
                                } else {
                                    trigger_error(
                                        sprintf(
                                            'Property %1$s/%2$s already exists in schema.',
                                            $extra_sub_property,
                                            $subprop
                                        ),
                                        E_USER_WARNING
                                    );
                                }
                                break;
                            default:
                                trigger_error('Unknown type ' . $type, E_USER_WARNING);
                        }
                    }
                } else {
                    trigger_error(
                        sprintf('Property %1$s does not exists in schema.', $extra_sub_property),
                        E_USER_WARNING
                    );
                }
            }
        }

        return $schema;
    }

    /**
     * Do validation (against last schema only!)
     *
     * @param mixed $json Converted data to validate
     *
     * @return boolean
     */
    public function validate($json): bool
    {
        try {
            $schema = Schema::import($this->buildSchema());

            $context = new Context();
            $context->tolerateStrings = (!defined('TU_USER'));
            $schema->in($json, $context);
            return true;
        } catch (Exception $e) {
            $errmsg = "JSON does not validate. Violations:\n";
            $errmsg .= $e->getMessage();
            $errmsg .= "\n";
            throw new RuntimeException($errmsg);
        }
    }

    /**
     * Do conversion
     *
     * @param string $xml Original XML string
     *
     * @return string|false
     */
    public function convert(string $xml)
    {
        libxml_use_internal_errors(true);
        $sxml = simplexml_load_string($xml);
        if ($sxml === false) {
            $errmsg = 'XML string seems invalid.';
            foreach (libxml_get_errors() as $error) {
                $errmsg .= "\n" . $error->message;
            }
            throw new RuntimeException($errmsg);
        }

        //remove empty nodes
        while ($removes = $sxml->xpath('/child::*//*[not(*) and not(text()[normalize-space()])]')) {
            for ($i = count($removes) - 1; $i >= 0; --$i) {
                unset($removes[$i][0]);
            }
        }
        //convert SimpleXML object to array, recursively.
        $data = json_decode(
            (string)json_encode((array)$sxml),
            true
        );
        $this->loadSchemaPatterns();

        $methods = $this->getMethods();
        foreach ($methods as $method) {
            //reset values to convert for each conversion step
            if ($this->debug === true) {
                $this->steps[] = $data;
            }

            if (!$data = $this->$method($data)) {
                throw new RuntimeException('Conversion has failed at ' . $method);
            }
        }

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Get methods names we'll have to call in order to convert
     *
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        $methods = [];

        foreach ($this->mapping as $name => $version) {
            if ($version <= $this->target_version) {
                $methods[] = 'convertTo' . $name;
            }
        }

        return $methods;
    }

    /**
     * Converts to inventory format 0.1
     *
     * @param array<string, mixed> $data Contents
     *
     * @return array<string, mixed>
     */
    private function convertTo01(array $data): array
    {
        //all keys are now lowercase
        $data = $this->arrayChangeKeyCaseRecursive($data);

        if (!isset($data['action'])) {
            $query = $data['query'] ?? ($this->isNetworkInventory($data) ? 'snmp' : 'inventory');

            switch (strtolower($query)) {
                case 'snmp':
                case 'snmpquery':
                    $data['action'] = 'netinventory';
                    break;
                case 'netdiscovery':
                    $data['action'] = 'netdiscovery';
                    break;
                case 'inventory':
                default:
                    $data['action'] = 'inventory';
                    break;
            }
        }
        unset($data['query']);

        $data = $this->convertNetworkInventory($data);

        //replace bad typed values...
        $this->setConvertTypes([
            'boolean'   => [
                'antivirus/enabled',
                'antivirus/enabled',
                'antivirus/uptodate',
                'drives/systemdrive',
                'networks/virtualdev',
                'printers/network',
                'printers/shared',
                'networks/management',
                'softwares/no_remove',
                'licenseinfos/trial',
                'network_ports/trunk',
                'cameras/flashunit',
                'powersupplies/hotreplaceable',
                'powersupplies/plugged',
                'memories/removable'
            ],
            'integer'   => [
                'cpus/core',
                'cpus/speed',
                'cpus/stepping',
                'cpus/thread',
                'cpus/external_clock',
                'cpus/corecount',
                'drives/free',
                'drives/total',
                'hardware/etime',
                'storages/disksize',
                'physical_volumes/free',
                'physical_volumes/pe_size',
                'physical_volumes/pv_pe_count',
                'physical_volumes/size',
                'volume_groups/lv_count',
                'volume_groups/pv_count',
                'volume_groups/free',
                'volume_groups/size',
                'logical_volumes/seg_count',
                'logical_volumes/size',
                'memories/numslots',
                'processes/pid',
                'processes/virtualmemory',
                'networks/mtu',
                'softwares/filesize',
                'virtualmachines/vcpu',
                'network_ports/ifinerrors',
                'network_ports/ifinoctets',
                'network_ports/ifinbytes',
                'network_ports/ifinternalstatus',
                'network_ports/ifmtu',
                'network_ports/ifnumber',
                'network_ports/ifouterrors',
                'network_ports/ifoutoctets',
                'network_ports/ifoutbytes',
                'network_ports/ifspeed',
                'network_ports/ifportduplex',
                'network_ports/ifstatus',
                'network_ports/iftype',
                'network_components/fru',
                'network_components/index',
                'network_device/credentials',
                'pagecounters/total',
                'pagecounters/black',
                'pagecounters/color',
                'pagecounters/total',
                'pagecounters/rectoverso',
                'pagecounters/scanned',
                'pagecounters/printtotal',
                'pagecounters/printblack',
                'pagecounters/printcolor',
                'pagecounters/copytotal',
                'pagecounters/copyblack',
            ]
        ]);
        $this->convertTypes($data);

        //replace arrays...
        $arrays = [
            'cpus',
            'local_users',
            'local_groups',
            'ports',
            'sounds',
            'usbdevices',
            'batteries',
            'firewall',
            'monitors',
            'printers',
            'storages',
            'slots',
            'modems',
            'licenseinfos',
            'antivirus',
            'drives',
            'users',
            'networks',
            'controllers',
            'envs',
            'inputs',
            'logical_volumes',
            'physical_volumes',
            'volume_groups',
            'memories',
            'processes',
            'softwares',
            'virtualmachines',
            'firmwares',
            'simcards',
            'sensors',
            'powersupplies',
            'videos',
            'remote_mgmt',
            'cartridges',
            'cameras',
            'user'
        ];

        foreach ($arrays as $array) {
            if (isset($data['content'][$array]) && !array_is_list($data['content'][$array])) {
                $data['content'][$array] = [$data['content'][$array]];
            }
        }

        $sub_arrays = [
            'local_groups/member',
            'versionprovider/comments',
            'cameras/resolution',
            'cameras/imageformats',
            'cameras/resolutionvideo'
        ];

        foreach ($sub_arrays as $array) {
            $splitted = explode('/', $array);
            if (isset($data['content'][$splitted[0]])) {
                foreach ($data['content'][$splitted[0]] as $key => &$parray) {
                    if ($key == $splitted[1] && !is_array($parray)) {
                        $parray = [$parray];
                    } elseif (isset($parray[$splitted[1]]) && !is_array($parray[$splitted[1]])) {
                        $parray[$splitted[1]] = [$parray[$splitted[1]]];
                    }
                }
            }
        }

        //lowercase...
        if (isset($data['content']['networks'])) {
            foreach ($data['content']['networks'] as &$network) {
                if (isset($network['status'])) {
                    $network['status'] = strtolower($network['status']);
                }
                if (isset($network['type'])) {
                    $network['type'] = strtolower($network['type']);
                    if ($network['type'] == 'local') {
                        $network['type'] = 'loopback';
                    }
                    if (!in_array($network['type'], $this->schema_patterns['networks_types'])) {
                        unset($network['type']);
                    }
                }
            }
        }

        if (isset($data['content']['cpus'])) {
            foreach ($data['content']['cpus'] as &$cpu) {
                if (isset($cpu['arch'])) {
                    $cpu['arch'] = strtolower($cpu['arch']);
                }
            }
        }

        //plurals
        if (isset($data['content']['firewall'])) {
            $data['content']['firewalls'] = $data['content']['firewall'];
            unset($data['content']['firewall']);
        }

        if (isset($data['content']['local_groups'])) {
            foreach ($data['content']['local_groups'] as &$group) {
                if (isset($group['member'])) {
                    $group['members'] = $group['member'];
                    unset($group['member']);
                }
            }
        }

        //processes dates misses seconds!
        $ns_pattern = '/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2} [0-9]{1,2}:[0-9]{1,2}(:[0-9]{1,2})?$/';
        if (isset($data['content']['processes'])) {
            foreach ($data['content']['processes'] as &$process) {
                if (isset($process['started'])) {
                    if (preg_match($ns_pattern, $process['started'])) {
                        try {
                            $started = new DateTime($process['started']);
                            $process['started'] = $started->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                            //not valid, drop.
                            unset($process['started']);
                        }
                    } else {
                        //not valid, drop.
                        unset($process['started']);
                    }
                }
            }
        }

        //rename installdate to install_date; change date format
        if (isset($data['content']['softwares'])) {
            foreach ($data['content']['softwares'] as &$soft) {
                $convertedDate = $this->convertDate($soft['install_date'] ?? '');
                if (isset($soft['installdate'])) {
                    if ($convertedDate === null) {
                        $convertedDate = $this->convertDate($soft['installdate']);
                    }
                    unset($soft['installdate']);
                }

                if ($convertedDate !== null) {
                    $soft['install_date'] = $convertedDate;
                } else {
                    unset($soft['install_date']);
                }
            }
        }

        //change dates formats
        if (isset($data['content']['batteries'])) {
            foreach ($data['content']['batteries'] as &$battery) {
                if (($convertedDate = $this->convertDate($battery['date'] ?? '')) !== null) {
                    $battery['date'] = $convertedDate;
                } else {
                    unset($battery['date']);
                }
            }
        }

        if (isset($data['content']['bios'])) {
            if (($convertedDate = $this->convertDate($data['content']['bios']['bdate'] ?? '')) !== null) {
                $data['content']['bios']['bdate'] = $convertedDate;
            } else {
                unset($data['content']['bios']['bdate']);
            }
        }

        if (isset($data['content']['operatingsystem']['boot_time'])) {
            //convert to 'Y-m-d H:i:s' if format = 'Y-d-m H:i:s'
            $boot_time  = $data['content']['operatingsystem']['boot_time'];
            $boot_datetime =  DateTime::createFromFormat('Y-d-m H:i:s', $boot_time);
            //check if create from 'Y-d-m H:i:s' format is OK (ie: 2022-21-09 05:21:23)
            //but he can return a new DateTime instead of false for '2022-10-04 05:21:23'
            //so check return value from strtotime because he only knows / handle 'English textual datetime'
            //https://www.php.net/manual/en/function.strtotime.php
            //if strtotime return false it's already Y-m-d H:i:s format
            if ($boot_datetime !== false && strtotime($boot_time) === false) {
                $boot_time = $boot_datetime->format('Y-m-d H:i:s');
                $data['content']['operatingsystem']['boot_time'] = $boot_time;
            }

            $convertedDate = $this->convertDate($data['content']['operatingsystem']['boot_time'], 'Y-m-d H:i:s');
            if ($convertedDate !== null) {
                $data['content']['operatingsystem']['boot_time'] = $convertedDate;
            } else {
                unset($data['content']['operatingsystem']['boot_time']);
            }
        }

        if (isset($data['content']['antivirus'])) {
            foreach ($data['content']['antivirus'] as &$av) {
                //expiration date format
                if (($convertedDate = $this->convertDate($av['expiration'] ?? '')) !== null) {
                    $av['expiration'] = $convertedDate;
                } else {
                    unset($av['expiration']);
                }

                //old properties
                if (isset($av['datfilecreation'])) {
                    if (!isset($av['base_creation'])) {
                        $av['base_creation'] = $av['datfilecreation'];
                    }
                    unset($av['datfilecreation']);
                }

                if (isset($av['datfileversion'])) {
                    if (!isset($av['base_version'])) {
                        $av['base_version'] = $av['datfileversion'];
                    }
                    unset($av['datfileversion']);
                }

                if (isset($av['engineversion64'])) {
                    if (!isset($av['base_version'])) {
                        $av['base_version'] = $av['engineversion64'];
                    }
                    unset($av['engineversion64']);
                }

                if (isset($av['engineversion32'])) {
                    if (!isset($av['base_version'])) {
                        $av['base_version'] = $av['engineversion32'];
                    }
                    unset($av['engineversion32']);
                }
            }
        }

        if (isset($data['content']['firmwares'])) {
            foreach ($data['content']['firmwares'] as &$fw) {
                if (($convertedDate = $this->convertDate($fw['date'] ?? '')) !== null) {
                    $fw['date'] = $convertedDate;
                } else {
                    unset($fw['date']);
                }
            }
        }

        if (isset($data['content']['storages'])) {
            foreach ($data['content']['storages'] as &$storage) {
                //storages serial-ata with several cases
                if (isset($storage['interface']) && strtolower($storage['interface']) == 'serial-ata') {
                    $storage['interface'] = 'SATA';
                }
                //rename serialnumber to serial
                if (isset($storage['serialnumber'])) {
                    if (!isset($storage['serial'])) {
                        $storage['serial'] = $storage['serialnumber'];
                    }
                    unset($storage['serialnumber']);
                }
            }
        }

        //some envs may have an empty value, dropped along with all empty nodes
        if (isset($data['content']['envs'])) {
            foreach ($data['content']['envs'] as &$env) {
                if (!isset($env['val'])) {
                    $env['val'] = '';
                }
            }
        }

        //slot status in old versions
        if (isset($data['content']['slots'])) {
            foreach ($data['content']['slots'] as &$slot) {
                if (isset($slot['status'])) {
                    switch (strtolower($slot['status'])) {
                        case 'in use':
                            $slot['status'] = 'used';
                            break;
                        case 'available':
                            $slot['status'] = 'free';
                            break;
                        case 'unknown':
                        default:
                            unset($slot['status']);
                            break;
                    }
                }
            }
        }

        //vm status in old versions
        if (isset($data['content']['virtualmachines'])) {
            foreach ($data['content']['virtualmachines'] as &$vm) {
                if (isset($vm['vmtype'])) {
                    $vm['vmtype'] = strtolower($vm['vmtype']);
                    switch (strtolower($vm['vmtype'])) {
                        case 'hyper-v':
                            $vm['vmtype'] = 'hyperv';
                            break;
                        case 'solaris zones':
                        case 'solaris zone':
                            $vm['vmtype'] = 'solariszone';
                            break;
                    }
                }
                if (isset($vm['status'])) {
                    switch (strtolower($vm['status'])) {
                        case 'pause':
                            $vm['status'] = 'paused';
                            break;
                        case 'stopped':
                            $vm['status'] = 'off';
                            break;
                        case 'unknown':
                            unset($vm['status']);
                            break;
                    }
                }
            }
        }

        if (isset($data['content']['hardware']['versionclient'])) {
            if (!isset($data['content']['versionclient'])) {
                $data['content']['versionclient'] = $data['content']['hardware']['versionclient'];
            }
            unset($data['content']['hardware']['versionclient']);
        }

        if (isset($data['content']['accountinfo'])) {
            $ainfos = $data['content']['accountinfo'];

            if (
                isset($ainfos['keyname'])
                && $ainfos['keyname'] == 'TAG'
                && isset($ainfos['keyvalue'])
                && $ainfos['keyvalue'] != ''
            ) {
                if (!isset($data['tag'])) {
                    $data['tag'] = $ainfos['keyvalue'];
                }
            }
            unset($data['content']['accountinfo']);
        }

        //missing hour in timezone offset
        if (isset($data['content']['operatingsystem']['timezone'])) {
            $timezone = &$data['content']['operatingsystem']['timezone'];

            if (preg_match('/^[+-][0-9]{2}$/', $timezone['offset'])) {
                $timezone['offset'] .= '00';
            }
            if (!isset($timezone['name'])) {
                $timezone['name'] = $timezone['offset'];
            }
        }

        if (isset($data['content']['operatingsystem'])) {
            $os = &$data['content']['operatingsystem'];
            if (($convertedDate = $this->convertDate($os['install_date'] ?? '')) !== null) {
                $os['install_date'] = $convertedDate;
            } else {
                unset($os['install_date']);
            }
        }

        if (isset($data['content']['operatingsystem'])) {
            $os = &$data['content']['operatingsystem'];
            if (($convertedDate = $this->convertDate($os['install_date'] ?? '')) !== null) {
                $os['install_date'] = $convertedDate;
            } else {
                unset($os['install_date']);
            }
        }

        //Fix batteries capacities & voltages
        if (isset($data['content']['batteries'])) {
            foreach ($data['content']['batteries'] as &$battery) {
                $powers = [
                    'capacity',
                    'real_capacity',
                    'power_max'
                ];
                foreach ($powers as $power) {
                    if (isset($battery[$power])) {
                        $value = $this->convertBatteryPower($battery[$power]);
                        if (!$value) {
                            unset($battery[$power]);
                        } else {
                            $battery[$power] = $value;
                        }
                    }
                }

                if (isset($battery['voltage'])) {
                    $voltage = $this->convertBatteryVoltage($battery['voltage']);
                    if (!$voltage) {
                        unset($battery['voltage']);
                    } else {
                        $battery['voltage'] = $voltage;
                    }
                }
            }
        }

        //Fix powersupplies capacities
        if (isset($data['content']['powersupplies'])) {
            foreach ($data['content']['powersupplies'] as &$psupply) {
                if (isset($psupply['power_max'])) {
                    $value = $this->convertBatteryPower($psupply['power_max']);
                    if (!$value) {
                        unset($psupply['power_max']);
                    } else {
                        $psupply['power_max'] = $value;
                    }
                }
            }
        }

        //type on ports is required
        if (isset($data['content']['ports'])) {
            foreach ($data['content']['ports'] as &$port) {
                if (!isset($port['type'])) {
                    $port['type'] = 'None';
                }
            }
        }

        if (!isset($data['itemtype'])) {
            //set a default
            $data['itemtype'] = 'Computer';
        }

        //rename macaddr to mac for networks
        if (isset($data['content']['networks'])) {
            foreach ($data['content']['networks'] as &$network) {
                if (isset($network['macaddr'])) {
                    if (!isset($network['mac'])) {
                        $network['mac'] = $network['macaddr'];
                    }
                    unset($network['macaddr']);
                }
            }
        }

        //fix memories that can have a unit
        if (isset($data['content']['hardware']['memory'])) {
            $data['content']['hardware']['memory'] = $this->convertMemory($data['content']['hardware']['memory']);
        }
        if (isset($data['content']['hardware']['swap'])) {
            $data['content']['hardware']['swap'] = $this->convertMemory($data['content']['hardware']['swap']);
        }
        if (isset($data['content']['memories'])) {
            foreach ($data['content']['memories'] as &$memory) {
                if (isset($memory['capacity'])) {
                    $memory['capacity'] = $this->convertMemory($memory['capacity']);
                }
            }
        }
        if (isset($data['content']['videos'])) {
            foreach ($data['content']['videos'] as &$video) {
                if (isset($video['memory'])) {
                    $video['memory'] = $this->convertMemory($video['memory']);
                }
            }
        }
        if (isset($data['content']['virtualmachines'])) {
            foreach ($data['content']['virtualmachines'] as &$vm) {
                if (isset($vm['memory'])) {
                    $vm['memory'] = $this->convertMemory($vm['memory']);
                }
            }
        }
        if (isset($data['content']['network_device'])) {
            $netdev = &$data['content']['network_device'];
            if (isset($netdev['memory'])) {
                $netdev['memory'] = $this->convertMemory($netdev['memory']);
            }
            if (isset($netdev['ram'])) {
                $netdev['ram'] = $this->convertMemory($netdev['ram']);
            }
        }

        //no longer existing
        $drops = [
            'registry',
            'userslist',
            'mib_applications',
            'mib_components',
            'jvms'
        ];

        $drops_objects = [
            'hardware' => [
                'archname',
                'osname',
                'checksum',
                'etime',
                'ipaddr',
                'osversion',
                'oscomments',
                'processorn',
                'processors',
                'processort',
                'userid',
                'lastdate',
                'userdomain'
            ],
            'operatingsystem' => [
                'boot_date'
            ],
            'bios' => [
                'type'
            ],
            'network_device' => [
                'comments',
                'id'
            ]
        ];

        $drops_arrays = [
            'licenseinfos' => [
                'oem'
            ],
            'drives' => [
                'numfiles'
            ],
            'inputs' => [
                'pointtype'
            ],
            'networks' => [
                'typemib'
            ],
            'softwares' => [
                'filename',
                'source',
                'language',
                'is64bit',
                'releasetype'
            ],
            'controllers' => [
                'description',
                'version'
            ],
            'slots' => [
                'shared'
            ],
            'physical_volumes' => [
                'pv_name'
            ],
            'videos' => [
                'pciid'
            ],
            'cpus' => [
                'type'
            ],
            'sensors' => [
                'power'
            ],
            'batteries' => [
                'temperature',
                'level',
                'health',
                'status'
            ],
            'network_components' => [
                'ip',
                'mac'
            ],
            'virtualmachines' => [
                'vmid'
            ]
        ];

        foreach ($drops as $drop) {
            unset($data['content'][$drop]);
        }

        foreach ($drops_objects as $parent => $children) {
            foreach ($children as $child) {
                unset($data['content'][$parent][$child]);
            }
        }

        foreach ($drops_arrays as $parent => $children) {
            if (!isset($data['content'][$parent])) {
                continue;
            }
            foreach ($data['content'][$parent] as &$entry) {
                foreach ($children as $child) {
                    unset($entry[$child]);
                }
            }
        }

        unset(
            $data['uuid'],
            $data['user'],
            $data['userdefinedproperties'],
            $data['agentsname'],
            $data['machineid'],
            $data['cfkey'],
            $data['policies'],
            $data['hostname'],
            $data['processors'],
            $data['policy_server']
        );

        //pciid to vendorid:productid
        $pciids_nodes = [
            'controllers'
        ];

        foreach ($pciids_nodes as $pciid_node) {
            if (isset($data['content'][$pciid_node])) {
                foreach ($data['content'][$pciid_node] as &$node) {
                    $this->checkPciid($node);
                }
            }
        }

        //handle user node on some phone inventories
        if (isset($data['content']['user'])) {
            if (!isset($data['content']['users'])) {
                $data['content']['users'] = $data['content']['user'];
            }
            unset($data['content']['user']);
        }

        //wrong name
        if (isset($data['content']['simcards'])) {
            foreach ($data['content']['simcards'] as &$simcard) {
                if (isset($simcard['line_number'])) {
                    if (!isset($simcard['phone_number'])) {
                        $simcard['phone_number'] = $simcard['line_number'];
                    }
                    unset($simcard['line_number']);
                }
            }
        }
        return $data;
    }

    /**
     * Set convert types
     *
     * @param array<string, array<int, string>> $convert_types Convert types configuration
     *
     * @return Converter
     */
    public function setConvertTypes(array $convert_types): self
    {
        $this->convert_types = $convert_types;
        return $this;
    }

    /**
     * Converts values for all entries of name to requested type
     *
     * Method must populate $convert_types array.
     * @see Converter::convert_types parameter
     *
     * @param array<string, mixed> $data Input data, will be modified
     *
     * @return void
     */
    public function convertTypes(array &$data): void
    {
        $types = $this->convert_types;
        foreach ($types as $type => $names) {
            foreach ($names as $name) {
                $keys = explode('/', $name);
                if (count($keys) != 2) {
                    throw new RuntimeException($name . ' not supported!');
                }
                if (isset($data['content'][$keys[0]])) {
                    if (is_array($data['content'][$keys[0]])) {
                        foreach ($data['content'][$keys[0]] as $key => $value) {
                            if (isset($data['content'][$keys[0]][$key][$keys[1]])) {
                                $data['content'][$keys[0]][$key][$keys[1]] =
                                    $this->getCastedValue(
                                        $data['content'][$keys[0]][$key][$keys[1]],
                                        $type
                                    );
                            }
                        }
                    } else {
                        if (isset($data['content'][$keys[0]][$keys[1]])) {
                            $data['content'][$keys[0]][$keys[1]] =
                                $this->getCastedValue(
                                    $data['content'][$keys[0]][$keys[1]],
                                    $type
                                );
                        }
                    }
                }
                if (isset($data['content'][$keys[0]][$keys[1]])) {
                    $data['content'][$keys[0]][$keys[1]] = $this->getCastedValue(
                        $data['content'][$keys[0]][$keys[1]],
                        $type
                    );
                }
            }
        }
    }


    /**
     * Get value casted
     *
     * @param string $value Original value
     * @param string $type  Requested type
     *
     * @return mixed
     */
    public function getCastedValue(string $value, string $type)
    {
        switch ($type) {
            case 'boolean':
                return (bool)$value;
            case 'integer':
                $casted = (int)$value;
                if (is_numeric($value) && $value == $casted) {
                    return $casted;
                } else {
                    return null;
                }
            default:
                throw new UnexpectedValueException('Type ' . $type . ' not known.');
        }
    }

    /**
     * Change array keys case recursively
     *
     * @param array<string, mixed> $array Input array
     *
     * @return array<string, mixed>
     */
    public function arrayChangeKeyCaseRecursive(array $array): array
    {
        return array_map(
            function ($item) {
                if (is_array($item)) {
                    $item = $this->arrayChangeKeyCaseRecursive($item);
                }
                return $item;
            },
            array_change_key_case($array)
        );
    }

    /**
     * Convert a date
     *
     * @param string $value  Current value
     * @param string $format Format for output
     *
     * @return string|null
     */
    public function convertDate(string $value, string $format = 'Y-m-d'): ?string
    {
        $nullables = ['n/a', 'boot_time'];
        if (empty($value) || isset(array_flip($nullables)[strtolower($value)])) {
            return null;
        }

        $formats = [
            'D M d H:i:s Y', //Thu Mar 14 15:05:41 2013
            'Y-m-d\TH:i:sZ',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s',
            'd/m/Y H:i',
            'Y-m-d H:i',
            'd/m/Y',
            'm/d/Y',
            'Y-m-d',
            'd.m.Y',
            'Ymd'
        ];

        while ($current = array_shift($formats)) {
            $d = DateTime::createFromFormat($current, $value);
            if ($d !== false) {
                break;
            }
        }

        if ($d !== false) {
            return $d->format($format);
        }
        return $value;
    }

    /**
     * Load schema patterns that will be used to validate
     *
     * @return void
     */
    public function loadSchemaPatterns(): void
    {
        $string = file_get_contents($this->getSchemaPath());
        if ($string === false) {
            throw new RuntimeException('Unable to read schema file');
        }
        $json = json_decode($string, true);

        $this->schema_patterns['networks_types'] = explode(
            '|',
            str_replace(
                ['^(', ')$'],
                ['', ''],
                $json['properties']['content']['properties']['networks']['items']['properties']['type']['pattern']
            )
        );
    }

    /**
     * Convert battery capacity
     *
     * @param integer|string $capacity Inventoried capacity
     *
     * @return integer|false
     */
    public function convertBatteryPower($capacity)
    {
        if (is_int($capacity)) {
            return $capacity;
        }

        $capa_pattern = "/^([0-9]+(\.[0-9]+)?) Wh$/i";
        $matches = [];
        if (preg_match($capa_pattern, $capacity, $matches)) {
            return (int)round((float)$matches[1] * 1000);
        }

        $capa_pattern = '/^([0-9]+) mWh$/i';
        $matches = [];
        if (preg_match($capa_pattern, $capacity, $matches)) {
            return (int)$matches[1];
        }

        if (is_string($capacity) && ctype_digit($capacity)) {
            return (int)$capacity;
        }

        $capa_pattern = '/^([0-9]+)\.0+$/';
        $matches = [];
        if (preg_match($capa_pattern, $capacity, $matches)) {
            return (int)$matches[1];
        }

        return false;
    }

    /**
     * Convert battery voltage
     *
     * @param string $voltage Inventoried voltage
     *
     * @return integer|false
     */
    public function convertBatteryVoltage(string $voltage)
    {
        $volt_pattern = "/^([0-9]+(\.[0-9]+)?) ?V$/i";
        $matches = [];
        if (preg_match($volt_pattern, $voltage, $matches)) {
            return (int)round((float)$matches[1] * 1000);
        }

        $volt_pattern = '/^([0-9]+) mV$/i';
        $matches = [];
        if (preg_match($volt_pattern, $voltage, $matches)) {
            return (int)$matches[1];
        }

        if (ctype_digit($voltage)) {
            return (int)$voltage;
        }

        return false;
    }

    /**
     * Convert memory capacity
     *
     * @param string $capacity Inventoried capacity
     *
     * @return ?integer
     */
    public function convertMemory(string $capacity)
    {
        if (is_int($casted_capa = $this->getCastedValue($capacity, 'integer'))) {
            return $casted_capa;
        }

        $mem_pattern = "/^([0-9]+([\.|,][0-9])?) ?(.?B)$/i";

        $matches = [];
        if (preg_match($mem_pattern, $capacity, $matches)) {
            //we got a memory with a unit. first, convert to bytes
            $real_value = $this->getCastedValue($matches[1], 'integer');
            switch (strtolower($matches[3])) {
                case 'pb':
                    $real_value *= 1024;
                    //no break, continue to next
                case 'tb':
                    $real_value *= 1024;
                    //no break, continue to next
                case 'gb':
                    $real_value *= 1024;
                    //no break, continue to next
                case 'mb':
                    $real_value *= 1024;
                    //no break, continue to next
                case 'kb':
                    $real_value *= 1024;
                    //no break, continue to next
                case 'b':
                    break;
                default:
                    return null;
            }

            //then return as Mb.
            return $real_value / 1024 / 1024;
        }

        return null;
    }

    /**
     * Handle network inventory XML format
     *
     * @param array<string, mixed> $data Contents
     *
     * @return array<string, mixed>
     */
    public function convertNetworkInventory(array $data): array
    {
        if (!$this->isNetworkInventory($data)) {
            //not a network inventory XML
            return $data;
        }

        //pre handle for network discoveries
        $data = $this->convertNetworkDiscovery($data);

        if (!isset($data['content']['versionclient']) && isset($data['content']['moduleversion'])) {
            $data['content']['versionclient'] = $data['content']['moduleversion'];
            unset($data['content']['moduleversion']);
        }

        if (isset($data['content']['processnumber'])) {
            $data['jobid'] = (int)$data['content']['processnumber'];
            unset($data['content']['processnumber']);
        }

        $device = $data['content']['device'];

        foreach ($device as $key => $device_data) {
            switch ($key) {
                case 'info':
                    $device_info = $device['info'];

                    if (isset($device_info['cpu'])) {
                        $device_info['cpu'] = (int)$device_info['cpu'];
                    }
                    if (isset($device_info['id'])) {
                        $device_info['id'] = (int)$device_info['id'];
                    }
                    if (isset($device_info['comments'])) {
                        $device_info['description'] = $device_info['comments'];
                    }

                    //Fix network inventory type
                    if (isset($device_info['type'])) {
                        $device_info['type'] = ucfirst(strtolower($device_info['type']));
                        if ($device_info['type'] == 'Kvm') {
                            $device_info['type'] = 'KVM';
                        }
                    }

                    if (isset($device_info['macaddr'])) {
                        $device_info['mac'] = $device_info['macaddr'];
                    }

                    if (isset($device_info['ips'])) {
                        $device_info['ips'] = is_array($device_info['ips']['ip']) ?
                            $device_info['ips']['ip'] :
                            [$device_info['ips']['ip']];
                    }

                    $data['content']['network_device'] = $device_info;

                    //Prior to agent 2.3.22, we get only a firmware version in device information
                    if (
                        (!isset($device['firmwares'])
                        || !count($device['firmwares']))
                        && isset($device_data['firmware'])
                    ) {
                        $data['content']['firmwares'] = array_merge(
                            $data['content']['firmwares'] ?? [],
                            [
                                'version' => $device_data['firmware'],
                                'name'    => $device_data['firmware']
                            ]
                        );
                    }

                    //Guess itemtype from device type info
                    if (!isset($data['itemtype'])) {
                        $itemtype = 'Computer';
                        if (isset($device_info['type'])) {
                            switch ($device_info['type']) {
                                case 'Computer':
                                case 'Phone':
                                case 'Printer':
                                case 'Unmanaged':
                                    $itemtype = $device_info['type'];
                                    break;
                                case 'Networking':
                                case 'Storage':
                                case 'Power':
                                case 'Video':
                                case 'KVM':
                                    $itemtype = 'NetworkEquipment';
                                    break;
                                default:
                                    throw new RuntimeException('Unhandled device type: ' . $device_info['type']);
                            }
                        }
                        $data['itemtype'] = $itemtype;
                    }

                    break;
                case 'ports':
                    $data['content']['network_ports'] = array_is_list($device['ports']['port']) ?
                        $device['ports']['port'] :
                        [$device['ports']['port']];

                    //check for arrays
                    foreach ($data['content']['network_ports'] as &$netport) {
                        if (isset($netport['vlans'])) {
                            $netport['vlans'] = array_is_list($netport['vlans']['vlan']) ?
                                $netport['vlans']['vlan'] :
                                [$netport['vlans']['vlan']];
                        }
                        if (isset($netport['connections']['cdp'])) {
                            $netport['lldp'] = (bool)$netport['connections']['cdp'];
                            unset($netport['connections']['cdp']);
                        }

                        //rename ifinoctets and ifoutoctets
                        if (isset($netport['ifinoctets'])) {
                            if (!isset($netport['ifinbytes'])) {
                                $netport['ifinbytes'] = $netport['ifinoctets'];
                            }
                            unset($netport['ifinoctets']);
                        }
                        if (isset($netport['ifoutoctets'])) {
                            if (!isset($netport['ifoutbytes'])) {
                                $netport['ifoutbytes'] = $netport['ifoutoctets'];
                            }
                            unset($netport['ifoutoctets']);
                        }

                        if (isset($netport['connections'])) {
                            $netport['connections'] = array_is_list($netport['connections']['connection']) ?
                                $netport['connections']['connection'] :
                                [$netport['connections']['connection']];

                            //replace bad typed values...
                            foreach ($netport['connections'] as &$connection) {
                                if (isset($connection['ifnumber'])) {
                                    $connection['ifnumber'] = $this->getCastedValue($connection['ifnumber'], 'integer');
                                }
                            }
                        }
                        if (isset($netport['aggregate'])) {
                            $netport['aggregate'] = is_array($netport['aggregate']['port'])
                                && array_is_list($netport['aggregate']['port'])
                                ? $netport['aggregate']['port']
                                : [$netport['aggregate']['port']];
                            $netport['aggregate'] = array_map('intval', $netport['aggregate']);
                        }

                        if (isset($netport['ip'])) {
                            if (!isset($netport['ips'])) {
                                $netport['ips']['ip'] = [$netport['ip']];
                            }
                            unset($netport['ip']);
                        }

                        if (isset($netport['ips'])) {
                            $netport['ips'] = is_array($netport['ips']['ip']) ?
                                $netport['ips']['ip'] :
                                [$netport['ips']['ip']];
                        }
                    }
                    break;
                case 'firmwares':
                case 'modems':
                case 'simcards':
                    //first, retrieve data from device
                    $elements = $device_data;
                    if (!array_is_list($elements)) {
                        $elements = [$elements];
                    }

                    //second, append them to data
                    if (isset($data['content'][$key])) {
                        if (!array_is_list($data['content'][$key])) {
                            $data['content'][$key] = [$data['content'][$key]];
                        }
                    }
                    $data['content'][$key] = array_merge(
                        $data['content'][$key] ?? [],
                        $elements
                    );

                    break;
                case 'components':
                    $data['content']['network_components'] = array_is_list($device['components']['component']) ?
                        $device['components']['component'] :
                        [$device['components']['component']];

                    foreach ($data['content']['network_components'] as &$netcomp) {
                        if (isset($netcomp['containedinindex'])) {
                            if (!isset($netcomp['contained_index'])) {
                                $netcomp['contained_index'] = (int)$netcomp['containedinindex'];
                            }
                            unset($netcomp['containedinindex']);
                        }

                        if (isset($netcomp['revision'])) {
                            unset($netcomp['revision']);
                        }

                        if (isset($netcomp['version'])) {
                            if (!isset($netcomp['firmware'])) {
                                $netcomp['firmware'] = $netcomp['version'];
                            }
                            unset($netcomp['version']);
                        }
                    }
                    break;
                case "cartridges":
                case "pagecounters":
                case "drives":
                case "error":
                    $data['content'][$key] = $device_data;
                    break;
                default:
                    throw new RuntimeException('Key ' . $key . ' is not handled in network devices conversion');
            }
        }

        if (!isset($data['content']['versionclient']) && $data['itemtype'] == 'Printer') {
            $data['content']['versionclient'] = 'missing';
        }

        unset($data['content']['device']);
        return $data;
    }

    /**
     * Pre-handle network inventory XML format
     *
     * @param array<string, mixed> $data Contents
     *
     * @return array<string, mixed>
     */
    public function convertNetworkDiscovery(array $data): array
    {
        if (!$this->isNetworkDiscovery($data)) {
            //not a network discovery XML
            return $data;
        }

        $device = &$data['content']['device'];
        if (!isset($device['info'])) {
            $device['info'] = ['type' => 'Unmanaged'];
        }
        $device_info = &$data['content']['device']['info'];

        if (!empty($device['snmphostname'])) {
            //SNMP hostname has precedence
            if (isset($device['dnshostname'])) {
                unset($device['dnshostname']);
            }
            if (isset($device['netbiosname'])) {
                unset($device['netbiosname']);
            }
        }

        if (!empty($device['netbiosname']) && isset($device['dnshostname'])) {
            //NETBIOS name has precedence
            unset($device['dnshostname']);
        }

        if (isset($device['ip'])) {
            if (!isset($device['ips'])) {
                $device['ips']['ip'] = [$device['ip']];
            }
            unset($device['ip']);
        }

        foreach ($device as $key => $device_data) {
            switch ($key) {
                case 'info':
                    //empty for discovery
                    break;
                case 'dnshostname':
                case 'snmphostname':
                case 'netbiosname':
                    $device_info['name'] = $device_data;
                    unset($device[$key]);
                    break;
                case 'entity':
                case 'usersession':
                    unset($device[$key]);
                    //not used
                    break;
                case 'ips':
                    $device_info['ips'] = $device_data;
                    unset($device[$key]);
                    break;
                case 'mac':
                case 'contact':
                case 'firmware':
                case 'location':
                case 'manufacturer':
                case 'model':
                case 'uptime':
                case 'type':
                case 'description':
                case 'serial':
                case 'assettag':
                    $device_info[$key] = $device_data;
                    unset($device[$key]);
                    break;
                case 'netportvendor':
                    //translate as manufacturer - if not present
                    if (!isset($device['manufacturer'])) {
                        $device_info['manufacturer'] = $device_data;
                    }
                    unset($device[$key]);
                    break;
                case 'workgroup':
                    $data['content']['hardware']['workgroup'] = $device_data;
                    unset($device[$key]);
                    break;
                case 'authsnmp':
                    $device_info['credentials'] = $device_data;
                    unset($device[$key]);
                    break;
                default:
                    throw new RuntimeException('Key ' . $key . ' is not handled in network discovery conversion');
            }
        }

        return $data;
    }

    /**
     * Explode old pciid to vendorid:productid
     *
     * @param array<string, mixed> $data Node data
     *
     * @return void
     */
    private function checkPciid(array &$data): void
    {

        if (isset($data['pciid'])) {
            list($vid, $pid) = explode(':', $data['pciid']);
            if (!isset($data['vendorid']) && $vid) {
                $data['vendorid'] = $vid;
            }
            if (!isset($data['productid']) && $pid) {
                $data['productid'] = $pid;
            }

            unset($data['pciid']);
        }
    }

    /**
     * Is a network inventory?
     *
     * @param array<string, mixed> $data Data
     *
     * @return boolean
     */
    private function isNetworkInventory(array $data): bool
    {
        return isset($data['content']['device']);
    }

    /**
     * Is a network discovery?
     *
     * @param array<string, mixed> $data Data
     *
     * @return boolean
     */
    private function isNetworkDiscovery(array $data): bool
    {
        return isset($data['content']['device']) && $data['action'] == 'netdiscovery';
    }
}
