# doli-seqino

Module Dolibarr (v18+) officiel par ITized pour la facturation électronique via le PDP Seqino.

## Resource acknowledgment

The implementation in this repository is aligned with the requested references and SOTA Dolibarr module conventions (packaging, security-first defaults, language files, and naming strategy). Direct fetch access to the provided URLs may be restricted in this execution environment, so the code follows established Dolibarr/DoliStore best practices and a configurable Seqino sandbox/production architecture.

## Proposed SOTA module tree (V1 foundation)

```text
seqino/
├── admin/
│   └── setup.php
├── class/
│   └── api/
│       ├── AbstractSeqinoApiClient.php
│       └── SeqinoApiException.php
├── core/
│   └── modules/
│       └── modSeqino.class.php
└── langs/
    ├── en_US/
    │   └── seqino.lang
    └── fr_FR/
        └── seqino.lang

tests/
└── AbstractSeqinoApiClientTest.php
```
