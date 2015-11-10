# JavaScriptUnpacker
Makes easy to unpack JavaScript code packed by Dean Edwards packer tool.  
Supporting all known encodings.  
Also, script can contain more then one encoded block and even the mixes with the no-encoded.

## Installation via Composer
```sh
composer require "mervick/javascript-unpacker"
```

## Usage
```php
echo JavaScriptUnpacker::unpack(file_get_contents('/path/to/packed.js'));
```

## License
MIT
