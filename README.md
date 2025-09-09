# FP-Social-Auto-Publisher

Questo progetto pubblica automaticamente contenuti sui social a partire da Trello.

## Generazione e configurazione di token e secret

1. Visita la pagina [https://trello.com/app-key](https://trello.com/app-key) e copia la tua **API Key**.
2. Dalla stessa pagina ottieni il **Secret** (client secret) e genera un **Token** cliccando sul link dedicato.
3. All'interno dell'editor del post type `tts_client` inserisci Key, Token e Secret nei campi dedicati del metabox *Client Credentials*.
4. Il Secret viene usato per validare le chiamate webhook. Trello invierà l'header `X-Trello-Webhook` firmato; in alternativa è possibile inviare un parametro `hmac` calcolato con `hash_hmac('sha256', $payload, $secret)`.

## Token Facebook e permessi

Per pubblicare contenuti su Facebook è necessario un **Page Access Token** dotato dei permessi `pages_manage_posts` e `pages_read_engagement`.

Inserisci nel campo *Facebook Access Token* del client il valore nel formato `{ID-pagina}|{access-token}` dove `{ID-pagina}` è l'identificativo della pagina su cui pubblicare.

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

## Pulizia dei log

Il plugin registra gli eventi nella tabella personalizzata `tts_logs`.
Ogni giorno viene eseguita automaticamente un'operazione di pulizia che elimina i
record più vecchi di un numero di giorni configurabile (30 giorni per impostazione predefinita).

Il periodo di conservazione è modificabile dalla pagina delle impostazioni del plugin tramite
il campo **Log Retention (days)**.

