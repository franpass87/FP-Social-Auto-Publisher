# FP-Social-Auto-Publisher

Questo progetto pubblica automaticamente contenuti sui social a partire da **multiple sorgenti di contenuto**. 

**🚀 NUOVE FUNZIONALITÀ - VERSIONE AGGIORNATA**

## ✨ Gestione Multi-Sorgente dei Contenuti

Il plugin ora supporta diverse fonti di contenuto, non solo Trello:

### 📁 Sorgenti Supportate
- **Upload Locale**: Carica file direttamente dal tuo computer con drag & drop
- **Dropbox**: Sincronizza automaticamente contenuti da cartelle Dropbox
- **Google Drive**: Importa contenuti da cartelle Google Drive
- **Trello**: Integrazione Trello (ora opzionale)
- **Editor Integrato**: Crea contenuti direttamente nell'interfaccia con l'editor WordPress

### 🎯 Interfaccia Interattiva
- **Nessun reload di pagina**: Tutte le operazioni avvengono via AJAX
- **Content Manager**: Dashboard dedicata per gestire tutti i contenuti
- **Preview in tempo reale**: Anteprima dei contenuti prima della pubblicazione
- **Filtri avanzati**: Cerca e filtra contenuti per cliente, stato, canale social
- **Programmazione flessibile**: Schedula pubblicazioni per date/orari specifici

### 🔧 Configurazione Semplificata
- **Trello Opzionale**: Non è più necessario configurare Trello per usare il plugin
- **Setup Cloud**: Configura facilmente Dropbox e Google Drive
- **Gestione Token**: Interfaccia semplificata per gestire i token di accesso

## ✨ Aggiornamenti Recenti

### Menu WordPress Riorganizzato
Il plugin ora utilizza una struttura di menu consolidata per migliorare la navigazione:
- **Menu principale**: "Social Auto Publisher" con tutte le funzioni organizzate come sottomenu
- **Dashboard migliorata**: Statistiche, post recenti, e azioni rapide
- **Design responsivo**: Interfaccia moderna e mobile-friendly
- **Pagine migliorate**: Calendar, Analytics, Health Status con styling avanzato

Vedi [MENU_STRUCTURE.md](MENU_STRUCTURE.md) per la documentazione completa della nuova struttura.

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

Canali supportati: `facebook`, `instagram`, `youtube`, `tiktok`, `blog`.

## Pubblicazione su Blog WordPress

Il plugin supporta la pubblicazione automatica di articoli su blog WordPress con le seguenti funzionalità:

### Configurazione Blog
Nel metabox *Client Credentials* è possibile configurare le impostazioni per la pubblicazione su blog tramite il campo **Blog Settings** nel formato:
```
post_type:post|post_status:draft|author_id:1|category_id:1|language:it|keywords:keyword1:url1|keyword2:url2
```

### Funzionalità Supportate
- **Supporto WPML**: Gestione automatica dei contenuti in italiano e inglese
- **Featured Image**: Collegamento automatico delle immagini allegate alle Trello card come immagini in evidenza
- **SEO WordPress**: Supporto per meta description, focus keyword, canonical URL e SEO title
- **Link Juicer**: Inserimento automatico di link per parole chiave specificate
- **Rilevamento Lingua**: Rilevamento automatico della lingua del contenuto (italiano/inglese)

### Parametri Configurabili
- `post_type`: Tipo di post WordPress (default: `post`)
- `post_status`: Stato del post (`draft`, `publish`, `private`)
- `author_id`: ID dell'autore del post
- `category_id`: ID della categoria del post
- `language`: Lingua del contenuto (`it`, `en`) per WPML
- `keywords`: Coppie parola chiave:URL per il link juicer (formato: `keyword1:url1|keyword2:url2`)
- `meta_description`: Meta description personalizzata per SEO
- `focus_keyword`: Parola chiave focus per SEO
- `canonical_url`: URL canonico personalizzato
- `seo_title`: Titolo SEO personalizzato

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

## 📖 Guida all'Uso delle Nuove Funzionalità

### Come Utilizzare il Content Manager

1. **Accesso**: Vai su "Social Auto Publisher" > "Content Manager" nel menu di WordPress
2. **Creazione Contenuti**: 
   - Usa il tab "Create Content" per scrivere contenuti con l'editor WordPress
   - Seleziona i canali social di destinazione
   - Programma la pubblicazione (opzionale)
3. **Upload File**: 
   - Usa il tab "Upload Files" per caricare immagini/video
   - Trascina i file nell'area di upload o clicca per selezionare
   - Compila titolo e descrizione
4. **Sincronizzazione Cloud**:
   - Configura Dropbox/Google Drive nelle impostazioni del cliente
   - Usa il tab "Sync Sources" per importare contenuti automaticamente
5. **Gestione Contenuti**:
   - Visualizza tutti i contenuti nel tab "Manage Content"
   - Filtra per cliente, stato o cerca per parole chiave
   - Modifica o elimina contenuti esistenti

### Configurazione Cloud Storage

#### Dropbox
1. Crea un'app su [Dropbox Developers](https://www.dropbox.com/developers/apps)
2. Genera un Access Token
3. Inserisci il token nel campo "Dropbox Access Token" del cliente
4. Specifica la cartella (es. "/Social Content")

#### Google Drive
1. Crea un progetto su [Google Cloud Console](https://console.cloud.google.com/)
2. Abilita Google Drive API
3. Crea credenziali OAuth 2.0
4. Genera un Access Token
5. Inserisci il token nel campo "Google Drive Access Token" del cliente
6. Specifica la cartella (es. "Social Content")

### Best Practices

- **Organizzazione File**: Mantieni i file organizzati nelle cartelle cloud
- **Nomi File**: Usa nomi descrittivi per i file (diventeranno titoli dei post)
- **Formati Supportati**: JPG, PNG, GIF, WebP, MP4, MOV, AVI per media
- **Backup**: Mantieni sempre backup dei contenuti importanti
- **Test**: Testa sempre le integrazioni prima dell'uso in produzione

