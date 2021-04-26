# Changelog

All notable changes to `flysystem-dropbox` will be documented in this file

## 2.0.4 - 2021-04-26

- avoid listing the base directory itself in listContents calls (#73)

## 2.0.3 - 2021-04-25

- make listing a non-created directory not throw an exception (#72)

## 2.0.2 - 2021-03-31

- use generator in listContents call for upstream compliance (#66)

## 2.0.1 - 2021-03-31

- fix bugs discovered after real-world use (#63)

## 2.0.0 - 2021-03-28

- add support from Flysystem v2

## 1.2.3 - 2020-12-27

- add support for PHP 8

## 1.2.2 - 2019-12-04

- fix `createSharedLinkWithSettings`

## 1.2.1 - 2019-09-14

- fix minimum dep

## 1.2.0 - 2019-09-13

- add `getUrl` method

## 1.1.0 - 2019-05-31

- add `createSharedLinkWithSettings`

## 1.0.6 - 2017-11-18

- determine mimetype from filename

## 1.0.5 - 2017-10-21

- do not throw an exception when listing a non-existing directory

## 1.0.4 - 2017-10-19

- make sure all files are retrieved when calling `listContents`

## 1.0.3 - 2017-05-18

- reverts changes made in 1.0.2

## 1.0.2 - 2017-05-18

- fix for files with different casings not showing up

## 1.0.1 - 2017-05-09

- add `size` key. `bytes` is deprecated and will be removed in the next major version.


## 1.0.0 - 2017-04-19

- initial release
