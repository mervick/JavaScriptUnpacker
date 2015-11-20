# JavaScriptUnpacker
Makes easy to unpacking JavaScript code packed by Dean Edwards packer tool.
Supports of all known encodings.
The input script may contain multiple encoded code blocks, mixes with the non-encoded code, and is also supported for repeatedly encoded code.

## Installation via Composer
```sh
composer require "mervick/javascript-unpacker"
```

## Usage
```php
echo JavaScriptUnpacker::unpack(file_get_contents('/path/to/packed.js'));
```

## Unpack JavaScript via PHP eval(function(p,a,c,k,e,d)
```php
$js =<<<JS
eval(function(p,a,c,k,e,r){e=String;if(!''.replace(/^/,String)){while(c--)r[c]=k[c]||c;k=[function(e){return r[e]}];e=function(){return'\\w+'};c=1};while(c--)if(k[c])p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c]);return p}('0.1(\'2 3!\');',4,4,'console|log|Hello|world'.split('|'),0,{}));
JS;

echo JavaScriptUnpacker::unpack($js);
// output: console.log('Hello world!');;
```

## Requirements
PHP >= 5.4

## License
MIT
