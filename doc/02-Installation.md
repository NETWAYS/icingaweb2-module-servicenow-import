# Installation

## Packages

NETWAYS provides this module via [https://packages.netways.de](https://packages.netways.de/).

To install this module, follow the setup instructions for the **extras** repository.

**RHEL or compatible:**

`dnf install icingaweb2-module-servicenow-import`

**Ubuntu/Debian:**

`apt install icingaweb2-module-servicenow-import`

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
