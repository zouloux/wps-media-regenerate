# WPS - Captcha

Will add a page in Wordpress admin to trigger all uploads regeneration.
Heavily inspired from [Regenerate Thumbnails plugin](https://wordpress.org/plugins/regenerate-thumbnails/) but way simpler.  

Can be found in wordpress admin under **Media > Regenerate Media**.

### Minimalist
- No admin message
- No buy plugin messages
- No setup

### Config
Can be disabled with an env `WPS_MEDIA_REGENERATE_DISABLE=true`

### Dependencies
It has no dependencies other than Bedrock and Wordpress.

### Install

How to install with [Bedrock](https://roots.io/bedrock/) :

```bash
composer require zouloux/wps-media-regenerate
```
