# FP-Social-Auto-Publisher

Questo progetto pubblica automaticamente contenuti sui social a partire da Trello.

## API richieste

- **Trello**: API Key e Token.
- **Meta (Facebook/Instagram)**: App ID e secret con permessi `pages_manage_posts`, `instagram_basic`, `pages_show_list`, `pages_read_engagement`.
- **YouTube**: OAuth client con scope `youtube.upload`.
- **TikTok**: App con scope `video.upload`.

## Generazione e configurazione di token e secret

1. Visita la pagina [https://trello.com/app-key](https://trello.com/app-key) e copia la tua **API Key**.
2. Dalla stessa pagina ottieni il **Secret** (client secret) e genera un **Token** cliccando sul link dedicato.
3. All'interno dell'editor del post type `tts_client` inserisci Key, Token e Secret nei campi dedicati del metabox *Client Credentials*.
4. Il Secret viene usato per validare le chiamate webhook. Trello invierà l'header `X-Trello-Webhook` firmato; in alternativa è possibile inviare un parametro `hmac` calcolato con `hash_hmac('sha256', $payload, $secret)`.

## Token Facebook e permessi

Per pubblicare contenuti su Facebook è necessario un **Page Access Token** dotato dei permessi `pages_manage_posts` e `pages_read_engagement`.

Inserisci nel campo *Facebook Access Token* del client il valore nel formato `{ID-pagina}|{access-token}` dove `{ID-pagina}` è l'identificativo della pagina su cui pubblicare.

## Token Instagram e ig_user_id

Per pubblicare contenuti su Instagram è necessario un account **Business** o **Creator** collegato a una pagina Facebook.

1. Crea un'app su [Meta for Developers](https://developers.facebook.com/apps/) e abilita l'**Instagram Graph API**.
2. Genera un **Access Token** con i permessi `instagram_basic`, `pages_show_list`, `instagram_content_publish` e `pages_read_engagement`. Puoi utilizzare il [Graph API Explorer](https://developers.facebook.com/tools/explorer/) per ottenere un token di prova e poi convertirlo in un token di lunga durata.
3. Recupera l'`ig_user_id` dell'account chiamando:

   ```
   https://graph.facebook.com/v17.0/{page-id}?fields=instagram_business_account&access_token={page-access-token}
   ```

   Il valore `instagram_business_account.id` è l'`ig_user_id`.
4. Inserisci nel campo *Instagram Access Token* del client il valore nel formato `{ig_user_id}|{access-token}`.

## Token TikTok Business

Per pubblicare contenuti su TikTok è necessario un account **Business** abilitato alle TikTok Marketing/Open API.

1. Crea un'app su [TikTok for Developers](https://developers.tiktok.com/).
2. Genera un **Access Token** con i permessi necessari per l'upload e la pubblicazione dei video.
3. Inserisci il token nel campo *TikTok Access Token* del client.

## Requisiti per la pubblicazione delle Stories

Per pubblicare Stories su Facebook e Instagram il file deve avere orientamento verticale:

- **Immagini**: 1080×1920 pixel (rapporto 9:16).
- **Video**: durata massima 60 secondi con risoluzione 1080×1920 pixel.

Quando è selezionato il flag *Pubblica come Story* il campo `_tts_story_media` deve contenere un file che rispetti questi requisiti.

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

Canali supportati: `facebook`, `instagram`, `youtube`, `tiktok`.

## Hashtag di default

Nel metabox *Client Credentials* del post type `tts_client` è possibile definire hashtag di default per Facebook, Instagram, YouTube e TikTok.
Gli hashtag indicati vengono automaticamente concatenati ai messaggi generati per il relativo canale.

## Pulizia dei log

Il plugin registra gli eventi nella tabella personalizzata `tts_logs`.
Ogni giorno viene eseguita automaticamente un'operazione di pulizia che elimina i
record più vecchi di un numero di giorni configurabile (30 giorni per impostazione predefinita).

Il periodo di conservazione è modificabile dalla pagina delle impostazioni del plugin tramite
il campo **Log Retention (days)**.

## Analytics

Una schermata dedicata mostra le metriche raccolte dai social per i post pubblicati.
Dal menu **Analytics** è possibile:

- filtrare i dati per canale e intervallo temporale;
- visualizzare i risultati in un grafico generato con Chart.js;
- esportare le metriche correnti in formato CSV.

