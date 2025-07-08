Original XML specification (network inventory)
=============================================

```
<!ELEMENT REQUEST (CONTENT+, DEVICEID, QUERY)>

  <!ELEMENT CONTENT (DEVICE*, MODULEVERSION, PROCESSNUMBER)>

    <!-- a device -->
    <!ELEMENT DEVICE (INFO, PORTS, MODEMS+, FIRMWARES+, SIMCARDS+,
    PAGECOUNTERS?, CARTRIDGES?)>

      <!-- generic information -->
      <!ELEMENT INFO (COMMENTS, CPU, FIRMWARE, ID, IPS, LOCATION, MAC, MEMORY,
      MODEL, NAME, RAM, SERIAL, TYPE, UPTIME, MANUFACTURER, CONTACT)>
        <!-- sysdescr (string) -->
        <!ELEMENT DESCRIPTION (#PCDATA)>
        <!-- CPU load in % (integer) -->
        <!ELEMENT CPU (#PCDATA)>
        <!-- firmware (string) -->
        <!ELEMENT FIRMWARE (#PCDATA)>
        <!-- device ID in GLPI (integer) -->
        <!ELEMENT ID (#PCDATA)>
        <!-- IP addresses list -->
        <!ELEMENT IPS (IP+)>
        <!-- an IP address, either IPv4 or IPv6 (string) -->
        <!ELEMENT IP (#PCDATA)>
        <!-- location (string) -->
        <!ELEMENT LOCATION (#PCDATA)>
        <!-- mac address (string) -->
        <!ELEMENT MAC (#PCDATA)>
        <!-- storage in Mio (integer) -->
        <!ELEMENT MEMORY (#PCDATA)>
        <!-- device model (string) -->
        <!ELEMENT MODEL (#PCDATA)>
        <!-- device name (string) -->
        <!ELEMENT NAME (#PCDATA)>
        <!-- volatile memory in Mio (integer) -->
        <!ELEMENT RAM (#PCDATA)>
        <!-- serial number (string) -->
        <!ELEMENT SERIAL (#PCDATA)>
        <!-- type
          (COMPUTER|NETWORKING|PRINTER|STORAGE|POWER|PHONE|VIDEO|KVM) -->
        <!ELEMENT TYPE (#PCDATA)>
        <!-- uptime ("X days, HH:MM::SS" format) -->
        <!ELEMENT UPTIME (#PCDATA)>
        <!-- device manufacturer -->
        <!ELEMENT MANUFACTURER (#PCDATA)>
        <!-- admin contact -->
        <!ELEMENT CONTACT (#PCDATA)>

      <!-- ports list -->
      <!ELEMENT PORTS (PORT*)>

        <!-- a port -->
        <!ELEMENT PORT (CONNECTIONS?, AGGREGATE?, IFDESCR, IFINERRORS,
        IFINOCTETS, IFINTERNALSTATUS, IFLASTCHANGE, IFMTU, IFNAME,
        IFNUMBER, IFOUTERRORS, IFOUTOCTETS, IFSPEED, IFPORTDUPLEX,
        IFSTATUS, IFTYPE, MAC, TRUNK, VLANS?)>

        <!-- connections list -->
        <!ELEMENT CONNECTIONS (CDP?, CONNECTION*)>
          <!-- LLDP/CDP connection? (0|1) -->
          <!ELEMENT CDP (#PCDATA)>

          <!-- a remote device connection, either as a list of known mac
          addresses, either as a CDP/LLDP/EDP information block -->
          <!ELEMENT CONNECTION (MAC+ | (IFNUMBER, SYSMAC, IFDESCR, IP,
          SYSDESCR, SYSNAME))>
            <!-- remote device mac address (string) -->
            <!ELEMENT MAC (#PCDATA)>
            <!-- remote device ifindex, from CDP/LLDP (integer) -->
            <!ELEMENT IFNUMBER (#PCDATA)>
            <!-- remote device mac address, from CDP/LLDP (string) -->
            <!ELEMENT SYSMAC (#PCDATA)>
            <!-- remote device port description, from CDP/LLDP (string) -->
            <!ELEMENT IFDESCR (#PCDATA)>
            <!-- remote device IP address, from CDP/LLDP (string) -->
            <!ELEMENT IP (#PCDATA)>
            <!-- remote device system description, from CDP/LLDP (string) -->
            <!ELEMENT SYSDESCR (#PCDATA)>
            <!-- remote device name, from CDP/LLDP (string) -->
            <!ELEMENT SYSNAME (#PCDATA)>

        <!-- aggregated ports list -->
        <!ELEMENT AGGREGATE (PORT+)>
          <!-- aggregated port ifindex (integer) -->
          <!ELEMENT PORT (#PCDATA)>

        <!-- description (string) -->
        <!ELEMENT IFDESCR (#PCDATA)>
        <!-- input errors number (integer) -->
        <!ELEMENT IFINERRORS (#PCDATA)>
        <!-- input bytes number (integer) -->
        <!ELEMENT IFINOCTETS (#PCDATA)>
        <!-- internal status (1|2|3) -->
        <!ELEMENT IFINTERNALSTATUS (#PCDATA)>
        <!-- time since last status change ("X days, HH:MM::SS" format) -->
        <!ELEMENT IFLASTCHANGE (#PCDATA)>
        <!-- MTU (integer) -->
        <!ELEMENT IFMTU (#PCDATA)>
        <!-- name (string) -->
        <!ELEMENT IFNAME (#PCDATA)>
        <!-- ifindex (integer) -->
        <!ELEMENT IFNUMBER (#PCDATA)>
        <!-- output errors number (integer) -->
        <!ELEMENT IFOUTERRORS (#PCDATA)>
        <!-- output bytes number (integer) -->
        <!ELEMENT IFOUTOCTETS (#PCDATA)>
        <!-- speed in bytes (integer) -->
        <!ELEMENT IFSPEED (#PCDATA)>
        <!-- duplex status (1|2|3)) -->
        <!ELEMENT IFPORTDUPLEX (#PCDATA)>
        <!-- port status (1|2) -->
        <!ELEMENT IFSTATUS (#PCDATA)>
        <!-- port type (integer) -->
        <!ELEMENT IFTYPE (#PCDATA)>
        <!-- mac address (string) -->
        <!ELEMENT MAC (#PCDATA)>
        <!-- trunk port flag (0|1) -->
        <!ELEMENT TRUNK (#PCDATA)>

        <!-- VLANs list -->
        <!ELEMENT VLANS (VLAN+)>
          <!-- a VLAN -->
          <!ELEMENT VLAN (NAME, NUMBER)>
            <!-- VLAN name (string) -->
            <!ELEMENT NAME (#PCDATA)>
            <!-- VLAN ID (integer) -->
            <!ELEMENT NUMBER (#PCDATA)>

      <!ELEMENT MODEMS (NAME, DESCRIPTION, TYPE, MODEL, MANUFACTURER, SERIAL)>
        <!-- modem name -->
        <!ELEMENT NAME (#PCDATA)>
        <!-- modem description if available -->
        <!ELEMENT DESCRIPTION (#PCDATA)>
        <!-- modem type -->
        <!ELEMENT TYPE (#PCDATA)>
        <!-- modem model -->
        <!ELEMENT MODEL (#PCDATA)>
        <!-- modem manufacturer -->
        <!ELEMENT MANUFACTURER (#PCDATA)>
        <!-- modem serial -->
        <!ELEMENT SERIAL (#PCDATA)>

      <!-- component firmwares -->
      <!ELEMENT FIRMWARES (NAME, DESCRIPTION, TYPE, VERSION, DATE, MANUFACTURER)>
        <!-- component name using the firmware -->
        <!ELEMENT NAME (#PCDATA)>
        <!-- firmware description if available -->
        <!ELEMENT DESCRIPTION (#PCDATA)>
        <!-- short description of firmware type: modem, bios, ... -->
        <!ELEMENT TYPE (#PCDATA)>
        <!-- full firmware version -->
        <!ELEMENT VERSION (#PCDATA)>
        <!-- firmware date -->
        <!ELEMENT DATE (#PCDATA)>
        <!-- firmware manufacturer -->
        <!ELEMENT MANUFACTURER (#PCDATA)>

      <!ELEMENT SIMCARDS (IMSI, PHONE_NUMBER, ICCID, STATE, COUNTRY, OPERATOR_CODE,
      OPERATOR_NAME)>
        <!-- IMSI -->
        <!ELEMENT IMSI (#PCDATA)>
        <!-- Phone number -->
        <!ELEMENT PHONE_NUMBER (#PCDATA)>
        <!-- ICCID (serial number) -->
        <!ELEMENT ICCID (#PCDATA)>
        <!-- State -->
        <!ELEMENT STATE (#PCDATA)>
        <!-- Country -->
        <!ELEMENT COUNTRY (#PCDATA)>
        <!-- Operator code -->
        <!ELEMENT OPERATOR_CODE (#PCDATA)>
        <!-- Operator Name -->
        <!ELEMENT OPERATOR_NAME (#PCDATA)>

      <!ELEMENT PAGECOUNTERS (TOTAL?, BLACK?, COLOR?, RECTOVERSO?, SCANNED?
      PRINTOTAL?, PRINTBLACK?, PRINTCOLOR?, COPYTOTAL?, COPYBLACK?,
      COPYCOLOR?, FAXTOTAL?)>
        <!ELEMENT TOTAL (#PCDATA)>
        <!ELEMENT BLACK (#PCDATA)>
        <!ELEMENT COLOR (#PCDATA)>
        <!ELEMENT RECTOVERSO (#PCDATA)>
        <!ELEMENT SCANNED (#PCDATA)>
        <!ELEMENT PRINTOTAL (#PCDATA)>
        <!ELEMENT PRINTBLACK (#PCDATA)>
        <!ELEMENT PRINTCOLOR (#PCDATA)>
        <!ELEMENT COPYTOTAL (#PCDATA)>
        <!ELEMENT COPYBLACK (#PCDATA)>
        <!ELEMENT COPYCOLOR (#PCDATA)>
        <!ELEMENT FAXTOTAL (#PCDATA)>

      <!-- CARTRIDGES node can content any meaningful printer cartridge info
      and it is up to the server to interpret given keys and values -->
      <!-- this element is deprecated, CONSUMABLES should be used instead -->
      <!ELEMENT CARTRIDGES ANY>
      <!-- Following lines are well-known samples, so they are kept as comment
        <!ELEMENT CARTRIDGEBLACK (#PCDATA)>
        <!ELEMENT CARTRIDGECYAN (#PCDATA)>
        <!ELEMENT CARTRIDGEMAGENTA (#PCDATA)>
        <!ELEMENT CARTRIDGEYELLOW (#PCDATA)>
        <!ELEMENT TONERBLACK (#PCDATA)>
        <!ELEMENT TONERCYAN (#PCDATA)>
        <!ELEMENT TONERMAGENTA (#PCDATA)>
        <!ELEMENT TONERYELLOW (#PCDATA)>
        <!ELEMENT WASTETONER (#PCDATA)>
        <!ELEMENT DRUMBLACK (#PCDATA)>
        <!ELEMENT DRUMCYAN (#PCDATA)>
        <!ELEMENT DRUMMAGENTA (#PCDATA)>
        <!ELEMENT DRUMYELLOW (#PCDATA)>
        <!ELEMENT DEVELOPERBLACK (#PCDATA)>
        <!ELEMENT DEVELOPERCYAN (#PCDATA)>
        <!ELEMENT DEVELOPERMAGENTA (#PCDATA)>
        <!ELEMENT DEVELOPERYELLOW (#PCDATA)>
        <!ELEMENT MAINTENANCEKIT (#PCDATA)>
        <!ELEMENT FUSERKIT (#PCDATA)>
        <!ELEMENT TRANSFERKIT (#PCDATA)>
        <!ELEMENT CLEANINGKIT (#PCDATA)>
        <!ELEMENT STAPLES (#PCDATA)>
        <!ELEMENT CARTRIDGEGRAY (#PCDATA)>
        <!ELEMENT CARTRIDGEDARKGRAY (#PCDATA)>
        <!ELEMENT CARTRIDGEMATTEBLACK (#PCDATA)>
        <!ELEMENT CARTRIDGEPHOTOBLACK (#PCDATA)>
        End of samples -->

      <!-- consumables list -->
      <!ELEMENT CONSUMABLES (CONSUMABLE)+>

        <!-- a single consumable -->
        <!ELEMENT CONSUMABLE (TYPE, COLOR, VALUE, UNIT)>
          <!ELEMENT TYPE (#PCDATA)>
          <!ELEMENT COLOR (#PCDATA)>
          <!ELEMENT VALUE (#PCDATA)>
          <!ELEMENT UNIT (#PCDATA)>
          <!-- use MAX if available, or assume it's 100 when UNIT is not set -->
          <!ELEMENT MAX (#PCDATA)>

    <!-- netinventory module version(string) -->
    <!ELEMENT MODULEVERSION (#PCDATA)>
    <!-- server process ID (integer) -->
    <!ELEMENT PROCESSNUMBER (#PCDATA)>

  <!-- agent ID (string) -->
  <!ELEMENT DEVICEID (#PCDATA)>
  <!-- message type, ie  "SNMPQUERY" -->
  <!ELEMENT QUERY (#PCDATA)>
```
