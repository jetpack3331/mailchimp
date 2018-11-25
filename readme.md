# MailChimp API pro Nette Framework

Nastavení v **config.neon**
```neon
extensions:
    smartEmailing: NAttreid\MailChimp\DI\MailChimpExtension

smartEmailing:
    apiKey: 'apiKey'
    listId: 'fs5f4s68e' # vychozi seznam pro ukladani kontaktu
    debug: true # default false
    dc: mailer # https://developer.mailchimp.com/documentation/mailchimp/guides/get-started-with-mailchimp-api-3/#resources
    store:
        id: 'storeId'
        name: 'storeName'
        domain: 'storeDomain'
        email: 'storeEmail'
        currency: 'storeCurrency'
```

Použití

```php
/** @var NAttreid\MailChimp\MailChimpClient @inject */
public $mailChimp;

```
