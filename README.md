# FP-Social-Auto-Publisher

Questo progetto pubblica automaticamente contenuti sui social a partire da Trello.

## Mappatura Trello → Canali Social

Nel metabox del custom post type `tts_client` è possibile definire una mappatura tra l'`idList` di Trello e il relativo `canale_social`.
La mappatura viene salvata nel meta `_tts_trello_map` come array serializzato da WordPress con la seguente struttura:

```json
[
  {
    "idList": "<ID della lista Trello>",
    "canale_social": "<canale social>"
  },
  {
    "idList": "<ID di un'altra lista>",
    "canale_social": "<altro canale social>"
  }
]
```

Ogni elemento dell'array associa una lista Trello al canale social su cui pubblicare.

