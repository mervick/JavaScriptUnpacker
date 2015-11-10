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

## Example
```php
$js =<<<JS
eval(function(p,a,c,k,e,r){e=String;if(!''.replace(/^/,String)){while(c--)r[c]=k[c]||c;k=[function(e){return r[e]}];e=function(){return'\\w+'};c=1};while(c--)if(k[c])p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c]);return p}('0.1(\'2 3!\');',4,4,'console|log|Hello|world'.split('|'),0,{}));
JS;

echo JavaScriptUnpacker::unpack($js);
// output: console.log('Hello world!');;
```

## License
MIT
