#!/bin/sh
composer update

rm -r dist
mkdir dist
cd dist
mkdir flmngr
cd flmngr
cp -rp ../../vendor .

cd vendor/edsdk/file-uploader-server-php
rm -r src
cp -rp ../../../../../../file-uploader-server-php/src .

cd ../
rm -r flmngr-server-php
mkdir flmngr-server-php
cd flmngr-server-php
cp -rp ../../../../../src .

cd ../../../../../dist
mkdir flmngr-example
mkdir flmngr/tmp
mkdir flmngr/cache
cp -rp ../files flmngr/
cp -rp ../flmngr.php flmngr/

cp -rp flmngr/flmngr.php flmngr-example/
cp -rp flmngr/vendor flmngr-example/
cp -rp flmngr/files flmngr-example/
zip flmngr-example.zip -r flmngr
