## Yama

Yama, a context-sensitive and path-sensitive interprocedural data flow analysis method for PHP, used to detect taint-style vulnerabilities in PHP applications. Our approach is based on the observation that PHP opcodes have precise semantics and clear control flow, which can make data flow analysis more precise and efficient.

## Installation

- **System**: Both Windows and Linux are supported. However, most of our testing has been conducted on **Windows**.
- **PHP Version**: We recommend using **PHP 8.0.25**. Other versions have not been tested and may cause unexpected issues.
- **Required PHP Extensions**:
  - `vld` (version 0.18.0): for extracting opcodes.
  - `parallel`: used for parallel execution during opcode extraction to improve performance.
  - `xdebug`: for enhanced debugging and tracing support.

## Usage

```
__     __
\ \   / /
 \ \_/ /_ _ _ __ ___   __ _
  \   / _` | '_ ` _ \ / _` |
   | | (_| | | | | | | (_| |
   |_|\__,_|_| |_| |_|\__,_|
Version: 1.0

Usage: php yama.php -t=<target> [-o=<output_file>] [-c=<1 or 0>] [-e=<1 or 0>] [-d=<1 or 0>] [-v=<1 or 0>]
Options:
  -t, --target            The path of the app you want to test
  -o, --output            Output file Path (default: ./result.html)
  -c, --cfgsave           Whether save cfg.dot for each file(default: false)
  -e, --extractopcode     Whether execute opcodes Extraction(default: true). If it has already been extracted, you can set false to improve the running speed
  -d, --debugmodel        Whether display debug information(default: false). Debugmodel will output a large amount of information during the execution of Yama. If debugging is required, it can be set to True
  -v, --verbose           Whether display analysis progress(default: false).
  -p, --pdf               Whether generate PDF report(need Pandoc, default: false).
  -h, --help              Show help
```

### Analyze dataset1
`php yama.php -t="..\Yama_datasets\dataset1\dataset1"`

### Analyze dataset2

`php yama.php -t="..\Yama_datasets\dataset2\C1_type_inference"`

`php yama.php -t="..\Yama_datasets\dataset2\C2_Dynamic_nature"`

`php yama.php -t="..\Yama_datasets\dataset2\C3_Buil-in_func"`

### Analyze Sample app

`php yama.php -t=".\app\DVWA-1.9"`

`php yama.php -t=".\app\glpi-10.0.16"`

## 📘 Documentation Status

This documentation is still in its early stages, and may contain omissions or incomplete sections. We are actively working to improve and expand the documentation.
