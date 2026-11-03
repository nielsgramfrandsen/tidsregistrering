# Tidsregistrering (PHP)

Simpel PHP-side med login og stopur. Tider gemmes pr. bruger og dag, med valgfri note, og vises i en liste og en graf. Virker på computer, tablet og mobil.

## Opsætning
1. Kopiér `inc/config.example.php` til `inc/config.php`.
2. Upload sitet til webserveren (Apache med `.htaccess`, eller IIS med `web.config`).
3. Åbn `hash.php` i browseren, lav en adgangskode-hash, sæt den ind i `inc/config.php`, og **slet `hash.php`** fra serveren.
4. Sørg for at `inc/data/` er skrivbar for webserveren.

`inc/config.php` og tidsdata (`inc/data/*.json`) ligger ikke i git.
