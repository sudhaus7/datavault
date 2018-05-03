Guard7
(old name datavault)

A Typo3 (8) Plugin to facilitate asymetric RSA encryption with multiple public keys 

!!! work in progress !!!
work in progress !!!

Working:

- Asymetric encryption / decryption of arbitrary text-fields in tables with several public keys.

- Public/Private Key management for FE Users

- Backend: Decryption on Client, List module and TCA Fields. 
- Rudimentary Support for Private Keys and Password encrypted Private Keys

- Tools for generating keys, validating keys and encrtyption/decryption in PHP and JS for Plugins.

- PageTS based confguration of to be encrypted database fields and tables.

- PageTS based configuration for additional Public Keys (not sure anymore if that is a good idea, input welcome )

- Commandline locking and unlocking for all configured fields

- Signal slot for collection of Public keys on encryption

- IRRE Support for non MM Relations

Installation
--

1. install the extension (via github for now):
add this to your composer json:
```
"repositories": [
  {
    "type": "vcs",
    "url": "git@github.com:sudhaus7/guard7.git"
  }
]
```

and for typo3 8.x

```
"require": {
  "SUDHAUS7/guard7": "dev-master"
}
```

for typo3 7.6 
```
"require": {
  "SUDHAUS7/guard7": "dev-typo76"
}
```

2. Enable the extension, choose your default Encryption. Recommended AES256, faster are AES128 or RC4. If you run Typo3 7.6 on php 5.6 you have to use RC4 or DEC. You can change this in the running environment later.

3. Switch to the Guard7 Module, and choose the Menu Button 'Generate a new Key'. On the new Screen either press the 2048 or 4096 button and wait a moment. Please store the Private Key somewhere save. Without it you will lose data, if it is the only key used in the system. Copy the Public Part of the key and switch back to the extensionmanager in Guard7's configuration.

4. Add the public part of your newly generate masterkey into the public key field of the Guard7 extension setup 

5. configure the pagets tree. Example Configuration, add to the PageTS:
```
tx_sudhaus7guard7 {
    generalPublicKeys {
       10 (
-----BEGIN PUBLIC KEY-----
Additional-base64-encoded-public-key
-----END PUBLIC KEY-----       
)
    }
    table_1 {
        fields = title,first_name,last_name,phone,mobile
    }
    table_2 {
        fields = first_name,last_name,email,passno,phone,birthplace,street,zip,city
        publicKeys {
        
        10 (
-----BEGIN PUBLIC KEY-----
Additional-base64-encoded-public-key
-----END PUBLIC KEY-----
)
        
        }
    }
}
```

Description
```
tx_sudhaus7guard7.[tablename].fields = fieldlist
```
(required) A comma separated list of fields for this table to encode. Can be extended. The encoding process will only run for tables and fields that are found in the PageTS tree for the referencing page

```
tx_sudhaus7guard7.[tablename].publicKeys = {}
```
(optional) An array if public keys, which will be used to encode data for this table. Only used if found in the PageTS tree for the referencing page

```
tx_sudhaus7guard7.generalPublicKeys = {}
```
(optional) An array of public keys, which will be used for every table. Only used if found in the PageTS tree for the referencing page

IRRE Tables must be listed like normal tables as well. The relation itself must not be encoded.

6. lock the Database:

per table:

Typo3 7.6:
```
DOCROOT#$  typo3/cli_dispatch.phpsh extbase  guard7:locktable --table=tablename
```

Typo3 8:
```
BASEDIR#$ vendor/bin/typo3 guard7:db:lock --table=tablename
```


TODO:
--
- IRRE MM Relations
- IRRE foreign field support
- Re-encoding of dirty datasets 
- documentation
- comments
- licenses
- Testing/proofing for 9.x
- Unittests (this should be no 1 todo..)
- the current system does not really work together with extbase's persistence workflow. The methods Storage::lockModel and Storage::unlockModel works, but need to be persisted twice with new models (no uid). Maybe something like extending from a different AbstractEntity could work. But I am wishing for a  container approach for dependency injection in the persistance layer.

- on domain/models will look into hooking into the persistence layers events



fberger@sudhaus7.de
@FoppelFB on Twitter
