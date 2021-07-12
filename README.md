# A concrete5 Add-On: CSV XML Converter

Generate Import Batch XML from CSV file.

## Features

* Upload CSV File and convert it to concrete5 Content Import Format XML File.
* Supported Content Type:
  * Pages
  * Users
* Not Supported Content Type (TODO):
  * Files

## Install

```bash
$ cd ./packages
$ git clone git@github.com:hissy/addon_csv_xml_converter.git csv_xml_converter
$ cd ../
$ ./concrete/bin/concrete5 c5:package-install csv_xml_converter
```
