Original XML specification (network discovery)
==============================================

```
<!ELEMENT REQUEST (CONTENT+, DEVICEID, QUERY)>

    <!ELEMENT CONTENT (DEVICE*, MODULEVERSION, PROCESSNUMBER)>

    <!-- a device -->
    <!ELEMENT DEVICE (IP, MAC?, AUTHSNMP?, TYPE?, MANUFACTURER?, MODEL?,
    DESCRIPTION?, SNMPHOSTNAME?, LOCATION?, CONTACT?, SERIAL?, FIRMWARE?,
    UPTIME?, IPS? DNSHOSTNAME?, NETPORTVENDOR?, NETBIOSNAME?, WORKGROUP?,
    USERSESSION?)>
        <!--  IP address -->
        <!ELEMENT IP (#PCDATA)>
        <!--  mac address -->
        <!ELEMENT MAC (#PCDATA)>

        <!-- Information retrieved from SNMP -->
        <!-- credentials ID -->
        <!ELEMENT AUTHSNMP (#PCDATA)>
        <!-- type
        (COMPUTER|NETWORKING|PRINTER|STORAGE|POWER|PHONE|VIDEO|KVM) -->
        <!ELEMENT TYPE (#PCDATA)>
        <!-- manufacturer -->
        <!ELEMENT MANUFACTURER (#PCDATA)>
        <!-- model -->
        <!ELEMENT MODEL (#PCDATA)>
        <!-- description (sysDescr) -->
        <!ELEMENT DESCRIPTION (#PCDATA)>
        <!--  host name -->
        <!ELEMENT SNMPHOSTNAME (#PCDATA)>
        <!--  location -->
        <!ELEMENT LOCATION (#PCDATA)>
        <!--  contact -->
        <!ELEMENT CONTACT (#PCDATA)>
        <!--  serial number -->
        <!ELEMENT SERIAL (#PCDATA)>
        <!--  firmware version -->
        <!ELEMENT FIRMWARE (#PCDATA)>
        <!--  uptime ("X days, HH:MM::SS" format) -->
        <!ELEMENT UPTIME (#PCDATA)>
        <!--  IP addresses -->
        <!ELEMENT IPS (IP+)>
        <!--  IP address -->
        <!ELEMENT IP (#PCDATA)>

        <!-- Information retrieved from Nmap -->
        <!-- host name -->
        <!ELEMENT DNSHOSTNAME (#PCDATA)>
        <!-- vendor -->
        <!ELEMENT NETPORTVENDOR (#PCDATA)>

        <!-- Information retrieved from NetBios -->
        <!-- host name -->
        <!ELEMENT NETBIOSNAME (#PCDATA)>
        <!-- user name -->
        <!ELEMENT USERSESSION (#PCDATA)>
        <!-- workgroup -->
        <!ELEMENT USERSESSION (#PCDATA)>

    <!-- netsdiscovery module version(string) -->
    <!ELEMENT MODULEVERSION (#PCDATA)> # netdiscovery module version
    <!-- server process ID (integer) -->
    <!ELEMENT PROCESSNUMBER (#PCDATA)>

    <!-- agent ID (string) -->
    <!ELEMENT DEVICEID (#PCDATA)> # agent ID (string)
    <!-- message type, ie "NETDISCOVERY" -->
    <!ELEMENT QUERY (#PCDATA)>
```
