# Installation

## From source

1. Clone the Icinga Web ServiceNow Import repository into `/usr/share/icingaweb2/modules/servicenowimport`

```sh
cd /usr/share/icingaweb2/modules
git clone https://github.com/NETWAYS/icingaweb2-module-servicenow-import.git servicenowimport
```

2. Enable the module using the `Configuration → Modules` menu or the `icingacli`

```sh
icingacli module enable servicenowimport
```
