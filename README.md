# deviceatlas

Unofficial Node Device Atlas API


## About

_Device Atlas:http://deviceatlas.com/ is a database of mobile device information. The official is available at http://deviceatlas.com/downloads. Unfortunately, there isn't an official API for Node or Javascript. 

## Install

This initial version is a wrapper around ```PHP```. Ouch, I know.

## Usage

```js
var DeviceAtlas = require('deviceatlas');
var DA = DeviceAtlas('DeviceAtlas.json');
var ua = 'SonyEricssonW850i/R1GB Browser/NetFront/3.3 Profile/MIDP-2.0 Configuration/CLDC-1.1';
var properties = DA.device(ua); // {u'gprs': '1', u'mpeg4': '1', u'drmOmaForwardLock': '1', ...
```

## To Do

Drop PHP dependency


## License

Dual:

- The official API holds it's own license. See files in ```DeviceApi/``` for details.
- This package: MIT
