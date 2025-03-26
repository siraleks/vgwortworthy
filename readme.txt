=== Worthy - VG WORT Integration für Wordpress ===
Contributors: tiggerswelt
Donate link: https://wp-worthy.de/
Tags: VG WORT, VG-Wort, T.O.M., METIS, Zählmarke, Zählpixel, Geld, Vergütung, Monetarisierung, VGW, VGWort, Verwertungsgesellschaft WORT
Requires at least: 4.6
Tested up to: 6.7
Stable tag: 1.7.4
Requires PHP: 7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Vereinfache die Arbeit mit VG WORT und verdiene einfacher Geld mit Deinen Texten als jemals zuvor.

== Description ==
> **Worthy ist die einzige für Wordpress verfügbare Lösung die alle Arbeitsschritte vom Importieren und Zuordnen von Zählmarken bis zur finalen Meldung an die VG WORT in Deinem Wordpress-Admin abbildet und massiv vereinfacht - sowohl für Autoren- wie auch Verlags-Konten!**

= Zielgruppe =
Worthy richtet sich an Autoren und Verlage, die ihre Texte online verfassen und regelmäßig an die VG WORT melden oder dies in Zukunft planen.
Dabei ist es unerheblich ob einer oder mehrere Autoren die Wordpress-Installation nutzen, ob diese durch einen Verlag vertreten werden oder ob alle Worthy Premium nutzen wollen oder nicht.
Jeder Autor kann individuell seine eigenen Präferenzen vorgeben oder auch zentral durch einen Verlag gemanaged werden.

Worthy vereinfacht die Arbeit mit Zählmarken der VG WORT indem es in der Grundversion eine Datenbank mit Zählmarken pflegt, diese Wordpress-Beiträgen zuordnet und so dafür sorgt, dass diese regelkonform im Blog ausgegeben werden.
Diverse Werkzeuge vereinfachen die Recherche innerhalb der Wordpress-Beiträge und der Zählmarken bei der täglichen Arbeit aber auch bei einer anstehenden Meldung an die VG WORT.

Abgerundet wird Worthy durch eine Premium-Funktion, die Dein Wordpress direkt mit der VG WORT verbindet und wesentliche Funktionen von dort direkt in Deinem Wordpress abbildet. Zum Beispiel ist **das Melden von Artikeln** kein Problem mit Worthy Premium, genau wie die Recherche nach Zählmarken in Abhängigkeit von ihrem Status.

Über Worthy Premium können Meldungen an die VG WORT vollkommen automatisiert durchgeführt werden - auch mit Autoren-Konten und auch wenn dies offiziell von der VG WORT nicht unterstützt wird. Einfach magisch!

= Funktionsumfang =

* Unterstützt Blogs mit mehreren Autoren und eigenen oder gemeinsam genutzten Zählmarken (beide Varainten in einem Blog)
* Unterstützt Multisite-Installationen vollständig, mit zentraler Zählmarken-Verwaltung für alle abgeleiteten Webseiten
* Separate Übersichten mit den Schwerpunkten Zählmarken wie auch Beiträgen
* Themenspezifische Filter- und Sortier-Funktionen
* Unterstützt die Auswahl bzw. Filterung von Beitrags-Typen und Shortcodes
* Bulk-Funktionen für Beiträge zum Zuordnen von Zählmarken oder Ignorieren für Worthy
* Integration mit Bewertung in Beitragsübersicht von Wordpress
* Native Integration in Gutenberg und Classic-Editor von Wordpress mit Zeichenzählung
* Import von Zählmarken aus CSV-Datei
* Export von Zählmarken in CSV-Datei, auch mit Beitrags-ID und -Überschrift
* Unterstützt Dich bei der Migration von Beiträgen in andere Wordpress-Installationen bzw. dem Zusammenführen mehrer Blogs
* Migration von Beiträgen mit eingebetteten Zählmarken
* Migration aus den Plugins "VG-Wort Krimskrams", "WP VG-Wort", "Prosodia VGW", "Torben Leuschners VG-Wort"
* Migration und Reparatur von Zählmarken die doppelt vergeben wurden
* Vorschau der zu migrierenden Beiträge
* Nachträglicher Import von privaten Zählmarken (nach Migration aus Quellen mit nur der öffentlichen Zählmarke)
* Schutz vor mehr als einer Zählmarke in der Blog-Ausgabe
* Unterstützt vollautomatisch HTTPS-gesicherte Weblogs
* Unterstützt diverse Accelerated Mobile Pages (AMP)-Plugins für Wordpress
* Unterstützt die Ausgabe von Zählmarken in Feeds (RSS2, etc.)
* Unterstützt die Ausgabe von Zählmarken über REST-Schnittstelle (als natives JSON-Attribut, wie auch im Beitragstext eingebettet)
* Ignorieren von Beiträgen und Zählmarken (keine Zählmarke für einen Beitrag ausgeben)
* Filtert unerwünschte Shortcodes bei der Zeichen-Zählung und Meldung von Beiträgen aus

**Worthy Premium**

* Bestellen von Zählmarken aus Wordpress heraus
* Synchronisation von Zählmarken (z.B. "Mindestzugriff erreicht")
* Recherche nach Zählmarken und Beiträgen anhand des Zählmarken-Status
* Anlegen von Webbereichen
* Automatisches Erstellen von Meldungen
* Repariert Zählmarken von denen nur der öffentliche Teil bekannt ist
* Personalisieren und Importieren von beliebig vielen anonymen Zählmarken (sofern die VG WORT dieses Feature anbietet, seit 2016 deaktiviert)
* Kostenfrei und ohne Verpflichtung für 7 Tage zu testen

= Migration von anderen Plugins =
Solltest Du von einem anderen Plugin zu Worthy wechseln wollen oder bisher Zählmarken direkt in den HTML-Code Deiner Beiträge integriert haben, so bietet Worthy Dir ein komfortabel zu bedienendes Migrations-Tool. Aktuell werden folgende Migrationspfade unterstützt:

* Im HTML-Code eingebettete Zählmarken
* Zählmarken aus dem Plugin "VGW (VG-Wort Krimskram)"
* Zählmarken aus dem Plugin "WP VG-Wort"
* Zählmarken aus dem Plugin "Prosodia VGW"
* Zählmarken aus dem Plugin "Torben Leuschners' VG-Wort"

Sobald du die Übersicht von Worthy aufrufst, prüft Worthy automatisch auf zu migrierende Beiträge und weist dich entsprechend darauf hin.

Wenn Du eine andere Einbettung der Zählmarken vorgenommen hast oder ein anderes, hier nicht gelistetes Tool verwendet hast, sprich unseren Support an!

Mit dem Migrations-Tool kannst Du entweder alle Beiträge, nur die eines bestimmten Plugins bzw. eingebettete Zählmarken bequem über eine Vorschau-Ansicht migrieren.
Wenn Du alle oder alle Beiträge einer bestimmten Methode migrierst, werden auch die eventuell vorhandenen freien Zählmarken mit migriert, Du musst also nicht alles nochmal neu importieren. Anders herum kann Worthy die privaten Zählmarken nicht erraten, sollten diese (z.B. bei eingebetten Zählmarken) nicht in Wordpress vorhanden sein. Hierzu kannst Du eventuell vorhandene CSV-Listen noch einmal importieren und so die privaten Zählmarken ergänzen.  
Nutzer von Worthy Premium erhalten etwaige fehlende private Zählmarken auch über die Synchronisation der Zählmarken. Diese wird nach der Registrierung für Worthy Premium automatisch ausgeführt.


Worthy ist ein von der VG WORT unabhängiges Plugin für Wordpress und wird von der VG WORT weder unterstützt noch vertrieben.

== Installation ==
Worthy lässt sich wie jedes normale Plugin aus dem Wordpress Plugin Repository installieren.

Alternativ kannst Du auch von [der Worthy-Webseite](https://wp-worthy.de/) die neuste Version herunterladen und als ZIP-Datei in Wordpress installieren:

1. Lade das Plugin als ZIP-Datei von [der Worthy-Webseite](https://wp-worthy.de/) herunter.
2. Lade die ZIP-Datei in Dein Wordpress hoch indem Du im Menü "`Plugins`" > "`Installieren`" und anschließend "`Plugin hochladen`" wählst.   Alternativ kannst Du die ZIP-Datei auch auf Deinem Computer entpacken und per FTP das Verzeichnis `wp-worthy` in das Plugin-Verzeichnis von Wordpress (`wp-content/plugins`) kopieren
3. Sobald das Plugin in Deinem Wordpress verfügbar ist, kannst Du es im Plugin-Bereich des Administrationsbackend aktivieren.
4. Alles weitere geschieht automatisch!

> **Systemanforderungen:**  
> Worthy wird stets auf der aktuellsten Wordpress-Version entwickelt, sollte aber mit allen Versionen ab 3.8 funktionieren. So oder so ist es aber ratsam stets die aktuellste Wordpress-Version zu verwenden. Die Entwicklung findet aktuell mit PHP 8.1 statt, gestestet wird aber auch auf PHP 8.0, 7.4, 7.3 und 7.2, alles ab PHP 7.0 sollte funktionieren.

== Changelog ==
Worthy befindet sich seit Herbst 2013 in der Entwicklung und wurde von zwei hauptberuflichen Autoren ausgiebig getestet. Wir sind uns relativ sicher, dass Worthy äußerst bereit für den Einsatz bei anderen Autoren ist.

Um Worthy noch besser zu machen, freuen wir uns über Dein Feedback. Dieses Changelog soll den Werdegang von Worthy abbilden, auch wenn aktuell das meiste bereits "im Verborgenen" geschehen ist.

= 1.7.4 =
* Veröffentlicht: 8. November 2024 gegen 00:00
* Bestellvorgang für Worthy Premium repariert
* Fehlerkorrektur zur Anzeige der ausstehenden Meldungen in der Menüleiste ("Badge")

= 1.7.3 =
* Veröffentlicht: 30. Juni 2024 gegen 18:00
* Giropay als Zahlweise entfernt
* Kleinere Veränderungen wenn kein Lazy-Loading der Zählmarke gewünscht ist

= 1.7.2 =
* Veröffentlicht: 5. März 2024 gegen 15:00
* Problem bei der Anzeige von Meldefähigen Artikeln behoben (#174)
* Fehler bei der Shortcode-Verarbeitung im Classic Editor und Gutenberg behoben (#176)

= 1.7.1 =
* Veröffentlicht: 1. März 2024 gegen 16:00
* Indexieren über "Administration"-Reiter war nicht mehr möglich (#175)
* Shortcode-Filter bei Ermittlung der Text-Länge überarbeitet (#167)
* Zählmarken-Bericht war auf Single-Site-Installationen ohne Zählmarken mit zugeordnetem Artikel (#172)
* Zugangsdaten für Worthy Premium können auch ohne aktives Abonnement gelöscht werden (#171)
* Erklärung zur Nutzung von KI wird vor Meldung abgefragt (#173)

= 1.7 =
* Veröffentlicht: 4. September 2023 gegen 15:00
* Cross-Site-Request-Forgery CVE 2023-24417 behoben
* Vernichten von Zählmarken war nicht möglich
* Unter bestimmten Umständen war es nicht möglich einen CSV-Export zu erstellen
* Das Vernichten von Zählmarken kann nun pauschal erlaubt werden

= 1.6.5 =
* Veröffentlicht: 7. Oktober 2022 gegen 13:00
* Fehler bim Indexieren von Artikeln behoben
* Kleinere Nachlässigkeit in Zählmarken-Ansicht behoben
* Webbereiche konnten nicht angelegt werden, jetzt wieder
* Im Editor wurde nicht mehr angezeigt ob ein Artikel ignoriert wurde
* Problem beim Verarbeiten von Shortcodes im Classic Editor behoben
* Die Mehrfachaktion "Ignorieren" war defekt

= 1.6.4 =
* Veröffentlicht: 13. September 2022 gegen 22:00
* **Es wird mindestens PHP 7.0 erfordert**
* Webbereiche können nun automatisch angelegt werden sobald eine Zählmarke einem Artikel zugeordnet wird (#113)
* Zählmarken können nun auch vor oder nach einem Artikel ausgegeben werden um Konflikte mit anderen Plugins zu vermeiden die den Filter "the_content" innerhalb des Main-Queries für andere Zwecke nutzen (#144)
* Problem bei der Anmeldung zu Worthy Premium mit Passwörtern die spezielle Zeichen enthalten behoben (#146)
* In seltenen Fällen konnte es passieren das ein "Broken Image"-Icon angezeigt wurde, wenn ein Tracking-Schutz oder Adblocker das Laden des Zählpixels verhinderte (#147)
* Behandlung von Fehlern wurde verbessert (#148)
* Fehler in Datenbank-Abfrage behoben der für lange Ladezeiten und leicht falsche Ergebnisse sorgte (#149)
* Im Reiter "Artikel" kann nun auch nach dem Artikel-Titel gesucht werden (#150)
* Potentieller Fehler mit dem Hinweis auf zu meldende Artikel in der Navigation behoben (#152)
* Fehler beim Abschneiden von überlangen Titeln behoben, wenn an 100. Stelle ein Sonderzeichen stand (#154)
* Bei der Zeichenzählung im Editor werden nun unerwünschte Shortcodes gefiltert (#160)
* Die Ausgabe von Zählmarken kann je nach Region-Einstellung nun verhindert werden (#161)
* In den Übersichten konnte keine Zählmarke zu geplanten Artikeln hinzugefügt werden (#162)
* In Gutenberg wird Worthy nun auch bei bereits geplanten Artikeln angezeigt
* Geplante Artikel werden nicht mehr als "Ungültiger Status" markiert
* Kleinere Korrekturen bei der Zeichenzählung bezüglich Sonderzeichen wie Umlaute
* Kleinere Korrekturen bei der Meldungsvorschau (Premium)

= 1.6.3.2 =
* Veröffentlicht: 16. September 2021 gegen 17:00
* Probleme mit Gutenberg als Editor für Widgets behoben (#138)
* Problem beim Synchronisieren von Zählmarken im Hintergrund beseitigt (#140)
* Migrations-Übersicht wird ausschließlich über einen Cronjob erstellt (#141)
* SQL-Funktion "SQL_CALC_FOUND_ROWS" wurde entfernt (für MySQL >= 8.0.17) (#139)
* Möglichkeiten zur Beeinflussung von lazy Loading erweitert (#142)
* Im Reiter "Premium" können nun die Zugangsdaten gelöscht werden ohne in den Debugging-Modus zu müssen (#143)
* Die VG WORT heißt jetzt überall wie sie heißt, außer bei Namen anderer Plugins

= 1.6.3.1 =
* Veröffentlicht: 20. August 2021 gegen 13:00
* Problem mit Versionen vor PHP 7.3 behoben

= 1.6.3 =
* Veröffentlicht: 19. August 2021 gegen 13:00
* In Gutenberg wird der Zeichen-Zähler nicht mehr doppelt angezeigt
* Automatisches zuordenen von Zählmarken funktioniert in Gutenberg wieder richtig
* Problem zwischen Gutenberg-Erweiterung von Worthy und Woocommerce behoben
* Zukünftig veraltete Funktionen in jQuery wurden angepasst
* Die Migration von Artikeln gibt nun ausführlicher Bescheid wenn etwas schief ging
* Bei der Migration wird nun immer versuch den Inhaber des Artikels als Zählmarken-Inhaber zu wählen sofern das Ursprungsplugin nichts anderes unterstützt
* Zählmarken von gelöschten Benutzern werden nun entfernt oder deaktiviert, je nachdem ob sie in Benutzung waren oder nicht
* Es können nun auch Zählmarken von gelöschten Benutzern wieder übernommen werden
* Einstellungen zur gemeinsamen Nutzung von Zählmarken werden entfernt wenn der teilende Benutzer gelöscht wird
* (Eventuell) Meldefähige Artikel werden automatisch neu indeziert um inkonsistente Metriken zu vermeiden
* Beiträge heißen jetzt Artikel
* Teils unnötige Informationen aus der Übersicht entfernt
* Einzelne Artikel in der Übersicht wurden ggf. doppelt angezeigt
* Problem mit Webseiten gelöst die über sehr viele Benutzer verfügen die nicht "Abonnenten" sind (z.B. WooCommerce Kunden)
* Cache für Artikel die gemeldet werden hinzugefügt
* Die URL zum Worthy-Premium-Webservice wurde angepasst

= 1.6.2.3 =
* Veröffentlicht: 8. April 2021 gegen 17:00
* Es kann nun nach ignorierten Beiträgen gefiltert werden
* Der Beitragstyp-Filter wird nicht mehr angezeigt wenn es nur eine Auswahlmöglichkeit gibt
* Das Worthy-Widget wird in Gutenberg nur noch für unterstützte Post-Typen angezeigt
* Einen weiteren Absturz in Gutenberg verhindert
* Reihenfolge der Zählmarken-Synchronisation angepasst
* Eine manuelle Synchronisation der Zählmarken lädt nun auch Zählmarken ohne Zählerstart

= 1.6.2.2 =
* Veröffentlicht: 30. März 2021 gegen 15:00
* Fehler in der REST-API von Worthy behoben der einen Absturz von Gutenberg zur Folge hatte
* Konstanten-Problem behoben

= 1.6.2.1 =
* Veröffentlicht: 29. März 2021 gegen 18:00
* Fatalen Fehler in der Migration behoben

= 1.6.2 =
* Veröffentlicht: 29. März 2021 gegen 17:00
* **Es wird mindestens PHP 5.4 erfordert**
* Wenn in der Beitrag-Ansicht von Wordpress Zählmarken eine Zählmarke zugeordnet werden soll und der Premium-Nutzer keine Zählmarken mehr hatte, funktioniert es nun
* Der Filter für Post-Typen in der Beitrags-Ansicht funktioniert nun wieder
* In der Beitrags-Ansicht wird auf nicht indizierte Beiträge hingewiesen, da diese die Ladegeschwindigkeit im Backend reduzieren können
* Optimierungen für Gutenberg
* Probleme beim reindizieren von Beiträgen behoben
* In Gutenberg kann wieder der Ignoriert-Status von Beiträgen entfernt werden
* Worthy-Benutzereinstellungen werden in Profilen von Abonnenten nicht mehr angezeigt
* Im Badge wurde irrtümlich auch auf Beiträge hingewiesen die den Mindestzugriff erreicht haben allerdings zu kurz waren
* Zeichenzähler wurde überarbeitet
* In der Übersicht werden die Migrations-Statistiken nicht mehr bei jedem Aufruf erstellt
* Beim Versuch Beiträge zu melden wurden nicht alle Fehlermeldungen ausgegeben

= 1.6.1 =
* Veröffentlicht: 27. Januar 2021 gegen 20:00
* Attribut im Zählpixel hinzugefügt um Lazy-Loading durch "Swift Performance" zu verhindern (wenn aktiviert)
* Wird eine Zählmarke in der Seiten- oder Beitragsansicht zugeordnet wird das Ergebnis sofort angezeigt
* Kleinere Fehlerkorrektur in der Zählmarken-Statistik
* Multisite-Fehlerkorrekturen für Webseiten ohne Multisite
* Etwaige Fehler anderer Plugins bei der Längen-Berechnung werden nun abgefangen

= 1.6 =
* Veröffentlicht: 25. Januar 2021 gegen 12:00
* Unterstützung für Multisite
* Referrer-Richtlinie gemäß Empfehlungen der VG WORT hinzugefügt
* Im Reiter "Zählmarken" werden Zählmarken von Autoren ohne Worthy Premium nicht mehr als "Nicht synchronisiert" angezeigt sofern ein anderer Autor über ein Abonnement verfügt
* Im Reiter "Beiträge" werden immer alle Beiträge mit Zählmarke angezeigt, unabhängig von Typ oder Status
* Im Reiter "Beiträge" werden im Filter nun Autoren mit Beiträgen und nicht nur Autoren mit Zählmarken
* Der Standard-Server für Zählmarken wird nun gründlicher geprüft um Fehleingaben zu verhindern
* Die Benutzer-Präferenz eine Zählmarke automatisch zuzuordnen oder nicht wird wieder berücksichtigt
* Es können nun alle Shortcodes für Worthy deaktiviert werden
* Worthy (mit Premium) ist toleranter beim Anzeigen welche Beiträge gemeldet werden können

= 1.5.6.1 =
* Veröffentlicht: 12. August 2020 gegen 00:00
* Feedback von wordpress.org umgesetzt

= 1.5.6 =
* Veröffentlicht: 9. August 2020 gegen 15:00
* Im Classic-Editor wird immer ein Schalter für Lyrik angezeigt, in Gutenberg wird das Widget korrekt entsprechend der Text-Art aktualisiert 
* Es können nun CSV-Dateien importiert werden die Worthy selbst mit Beitragstitel (in einer dritten Spalte) erstellt hat
* Der CSV-Import der Zählmarken-Recherche ist nun insgesamt etwas tolertanter
* Benutzer-Einstellungen von Wordpress werden nur ergänzt wenn das Profil bearbeitet wird und der Benutzer Beiträge publizieren darf
* Alle Benutzer-Eingaben werden ausnahmslos überprüft. Das war in der Vergangenheit leider nicht bei allen Werten der Fall.

= 1.5.5.1 =
* Veröffentlicht: 26. Mai 2020 gegen 11:00
* Kritischer Fehler im Zusammenhang mit der Accountfreigabe behoben

= 1.5.5 =
* Veröffentlicht: 25. Mai 2020 gegen 19:00
* Administratoren können Worthy jetzt verbieten Lazy-Loading auszuhebeln
* Dem Bild-Element des Zählpixels können nun eigene CSS-Klassen hinzugefügt werden
* Das Vernichten von Zählmarken funktionierte nicht richtig
* Es kann nun ein Standard-Benutzerkonto festgelegt werden aus dem alle anderen Konten ohne eigene Zählmarken Zählmarken verwenden sollen
* Einige Zählmarken-Operationen prüfen nun ob der auslösende Autor auch mit dem Beitrag verbunden ist
* Worthy Premium weißt nun auf Zählmarken hin die den Mindestzugriff erreicht haben aber Worthy selbst unbekannt sind

= 1.5.4.1 =
* Veröffentlicht: 31. Januar 2020 gegen 15:00
* Zählmarken mit Autoren-Konflikt können nun vernichtet werden
* Javascript wird nach Update automatisch neu geladen
* Nach manueller Synchronisierung der Zählmarken wird die Seite neu geladen
* Der Zeichenindex wird nun auch nach einer Bearbeitung in Gutenberg aktualisiert
* Worthy Premium: Beim Melden wird im Fehlerfall nun eine Fehlermeldung angezeigt
* Worthy Premium: Im Fehlerfall wird der Status einer Zählmarke nicht fehlerhaft gespeichert

= 1.5.4 =
* Veröffentlicht: 20. Januar 2020 gegen 19:00
* In der Beitrag-Ansicht wird der Server einer Zählmarke nur angezeigt wenn die Zählmarke einen individuellen Server nutzt
* In der Zählmarken-Ansicht wird die URL nun immer dynamisch entsprechend der Konfiguration erzeugt, d.h. auch Verlagszählmarken erhalten eine URL
* In Gutenberg können Zählmarken-Zuordnungen nun auch nach Veröffentlichung bearbeitet werden
* In der Beitrag-Tabelle werden nun auch zugeordnete Zählmarken angezeigt selbst wenn der Beitrag keine Zählmarke ausspielen soll
* Im Tab "Administration" kann nun eine Zählmarken-Operation auf alle Benutzer auf einmal angewendet werden
* "loading"-Attribut zu Zählmarke hinzugefügt um "lazy loading" zu vermeiden
* Fehler in der REST-Schnittstelle behoben
* Zählmarken werden in Worthy Premium nun in Reihenfolge einer subjektiven Gewichtung nach aktualisiert
* Zählmarken ohne Zählerstart werden über Worthy Premium nicht mehr synchronisiert
* Die Zählmarken-Synchronisierung findet nach Möglichkeit per Ajax/XHR und in mehrerern Anfragen statt
* In den Benutzer-Einstellungen von Wordpress kann nun ein "Gemeinsamer Zugang" zu VG WORT-Zählmarken ausgewählt werden
* In den Benutzer-Einstellungen können Zählmarken für andere Benutzer importiert werden

= 1.5.3 =
* Veröffentlicht: 24. November 2019 gegen 00:00
* Die Zuordnung von Zählmarken in Gutenberg schlug mit Wordpress 5.3 fehl
* In der REST-API sind nun weitere Informationen zur gesetzten Zählmarke enthalten
* Zählmarken können ebenfalls optional direkt in die REST-API eingebettet werden
* Der Block-Editor (Gutenberg) berücksichtigt nun die Präferenz eine Zählmarke automatisch zuzuordnen
* Kleinere Fehlerkorrektur im Classic Editor
* Fehlerkorrektur für den Import von Prosodia aus
* Zählmarken die per HTTPS ausgegeben werden verwenden nun ihren ursprünglichen Server
* Die Aktualisierung von Worthy Premium geschieht nun im Hintergrund
* In der Übersicht wird immer der Inhaber der jeweiligen Zählmarken angezeigt
* Wenn Worthy Premium nicht nutzbar ist, wird die Ursache hierfür nun differenzierter dargestellt
* Ist kein Guthaben für Meldungen vorhanden wird darauf im Bericht hingewiesen
* Das class-Attribut des Zählpixels wird nun als erstes Attribut ausgegeben
* Die Optionen im Menü "Administration" wurden neu gruppiert

= 1.5.2 =
* Veröffentlicht: 1. April 2019 gegen 16:00
* Zählmarken können nun auch in den RSS2-Feed eingebettet werden
* Die Position der Zählmarke in der Ausgabe kann nun unter Administration bestimmt werden
* <img/>-Tag der Zählmarke trägt nun die CSS-Klasse "wp-worthy-pixel-img" sowie ein leeres alt-Attribut (um SEO-Dienste etwas zu beruhigen)
* Für AMP-Zählpixel wird nun immer eine HTTPS-URL ausgegeben
* Geplanten Artikeln kann nun ebenfalls eine Zählmarke zugewiesen werden
* Beiträge die doppelte Meta-Werte haben werden nun nicht mehr doppelt gezählt und/oder angezeigt
* Bugfix für Plugin "AMPforWP" (accelerated-mobile-pages)

= 1.5.1 =
* Veröffentlicht: 29. Januar 2019 gegen 00:00
* Fehlermeldung zum automatischen Hintergrundcheck wurde ausgeblendet bis das zuverlässig funktioniert
* Zählmarke sollte nie innerhalb eines Absatzes platziert werden
* Tooltips zu Zählmarken-Status hinzugefügt
* Mit Worthy Premium wird das Anlegen von Webbereichen nur noch auf Wunsch angezeigt

= 1.5 =
* Veröffentlicht: 18. November 2018 gegen 00:00
* Support für Wordpress 5.0 und Gutenberg
* Kleinerer Bugfix in der Selbstcheck-Funktion, da deaktivierte Zählmarken dennoch geprüft wurden

= 1.4.9 =
* Veröffentlicht: 5. September 2018 gegen 12:00
* Worthy prüft sich nun hin und wieder selbst und warnt sollte es ein Problem mit der Ausgabe von Zählmarken geben
* Initialize Unterstützung für einige AMP-Plugins
* Filter für Shortcodes hinzugefügt. Shortcodes können von der Zeichenzählung und der Meldung an die VG WORT über Worthy Premium ausgenommen werden
* Zählmarken können nun auch im Export in das HTML der Beiträge eingebettet werden
* Zählmarken werden etwas aggressiver ausgegeben
* Kleinere Fehlerkorrekturen - eine Variable war falsch benannt, ein Meta-Eintrag ggf. wurde mehr als einmal pro Post angelegt

= 1.4.8 =
* Veröffentlicht: 14. Januar 2018 gegen 17:00
* Hinweis zu Zählmarken in Übersichtstabelle bei nicht veröffentlichten Beiträgen spezifiziert
* Überlange Titel von Beiträgen können im CSV-Export und bei einer Meldung über Worthy Premium automatisch gekürzt werden
* Worthy Premium kann besser mit abgelaufenen Abonnements umgehen
* Kleinere Fehlerkorrekturen

= 1.4.7 =
* Veröffentlicht: 9. November 2017 gegen 18:00
* Kompatibilität mit Wordpress 4.9 getestet
* Benutzer-Einstellungen finden sich nun auch in der Benutzer-Verwaltung von Wordpress (nur für Premium relevant im Moment)
* Worthy prüft nun genauer ob bereits irgend eine bekannte Zählmarke in einem Beitrag vorhanden ist
* Bugfix für den Fall, dass sich die VG WORT-Zugangsdaten geändert haben
* Worthy-Premium nutzt für Verlagsmeldungen nun Vor- und Nachname aus dem Benutzer-Profil sofern nicht explizit andere Angaben gemacht wurden
* Worthy-Premium weißt nun darauf hin, wenn auf T.O.M. Nachrichten vorliegen die vom Benutzer bestätigt werden müssen
* Worthy-Premium weißt nun darauf hin, wenn die Nutzung wegen Änderung der Teilnahmebedingungen eingeschränkt ist

= 1.4.6.1 =
* Veröffentlicht: 21. März 2017 gegen 14:00
* Kompatibilität mit PHP 5.3 wieder hergestellt

= 1.4.6 =
* Veröffentlicht: 17. März 2017 gegen 00:00
* Ausgabe von Zählmarken in Beiträgen überarbeitet und robuster gemacht
* Vorbereitungen für Multi-Site-Unterstützung (nicht abgeschlossen)
* Kleinere Geschwindigkeitsoptimierungen

= 1.4.5 =
* Veröffentlicht: 16. Januar 2017 gegen 22:00
* In der Beitrags-Übersicht kann nach meldefähigen Artikeln gefiltert werden
* Die Anzahl der meldefähigen Beiträge werden nun im Dashboard wie auch in der Übersicht angezeigt
* In der Übersicht werden nun ein paar Metriken zu Worthy-Premium angezeigt
* In der Vorschau einer Meldung wurde der Autor nicht mehr angezeigt
* Vorläufiger Workaround für Übersetzungs-Probleme

= 1.4.4 =
* Veröffentlicht: 16. Dezember 2016 gegen 10:00
* In der Übersicht werden Zählmarken ohne privatem Identifikationscode nun pro Benutzer angezeigt
* Die Übersichtstabellen berücksichtigen den Lyrik-Status von Beiträgen und zeigen hier nicht mehr unnötig eine Warnung an
* Die Zählung freier Zählmarken berückstichtigt nun auch durchgehend den Status der Zählmarke bei der VG WORT (Zählmarken mit Zählerstart werden keinen neuen Beiträgen zugeordnet)
* Kleinere Fehlerkorrekturen und Stabilitätsverbesserungen

= 1.4.3 =
* Veröffentlicht: 10. August 2016 gegen 17:00
* Worthy sucht auch im Excerpt nach eingebetteten Zählmarken
* Autoren-Konflikte für Beiträge ohne Zählmarke wurden nicht korrekt erkannt
* Fehler bei Import von Verlagszählmarken behoben
* Zeichenzähler im Beitrag-Editor wurde verbessert
* Probleme mit dem Worthy-Shop bei abgelaufenen Test-Konten wurden beseitigt
* Das Werkzeug zum indexieren von Beiträgen findet sich nun im Reiter "Administration"
* Zählmarken ohne Benutzer-Zuordnung können nun einem bestimmten Benutzer zugeordnet werden
* Kleinere Bug-Fixes
* Worthy-Premium: VG WORT bietet aus bisher unbekannten Gründen keine anonymen Zählmarken mehr an und auch keine Möglichkeit diese zu personalisieren. Eine Meldung muss hier manuell erfolgen.
* Worthy-Premium: Worthy kann nun selbstständig nach fehlenden privaten Zählmarken suchen und unvollständige Zählmarken so ergänzen.
* Kompatibilität zu Wordpress 4.6 getestet und für gut befunden

= 1.4.2 =
* Veröffentlicht: 28. Juni 2016 gegen 14:00
* Worthy kann nun mehr Beitragstypen als nur "Posts" und "Pages" berücksichtigen
* Die Worthy-Spalte der Wordpress-Beitrag-Übersicht wurde überarbeitet
* Die Beitrag-Übersicht weißt nun darauf hin, wenn der jeweilige Beitrag von einem anderen Autor verfasst wurde, zu dem keine Beziehung innerhalb von Worthy besteht
* In der Zählmarken-Übersicht werden erst einmal nur noch Zählmarken angezeigt, die nicht irgendwie ignoriert wurden
* In der Zählmarken-Übersicht wird nun ein Hinweis angezeigt, wenn kein privater Identifikationscode vorhanden ist. Etwaige Worthy-Premium-Aktionen werden bei fehlendem Identifikationscode ausgeblendet.
* In beiden Übersicht-Tabellen können Beiträge nun direkt und einzeln ignoriert werden
* Zusätzlicher Schutz zur Verhinderung doppelter Einbindung von Zählmarken
* Bei längeren Dialogen wird eine zusätzliche Navigation eingeblendet
* Kleinere Bug-Fixes
* Worthy-Premium: Es werden auch Zählmarken ohne Zählerstart synchronisiert
* Worthy-Premium: Für Zählmarken mit zugeordnetem Beitrag aber ohne Zählerstart kann nun auch ein Webbereich angelegt werden
* Worthy-Premium: In der Aktionsspalte der Zählmarken-Übersicht erscheint nun ein Hinweis wenn der Titel zu lang ist

= 1.4.1 =
* Veröffentlicht: 07. Juni 2016 gegen 16:00
* Zählpixel von Lazy-Loading ausgenommen für bessere Kompatibilität mit WP-Rocket und MaxCDN
* Fehlerkorrektur für Filter in der Beitrag-Ansicht
* Fehlerkorrektur beim Neuindezieren aller Beiträge
* Fehlerkorrektur bzgl. Beitrag-Formattierung in seltenen Fällen

= 1.4 =
* Veröffentlicht: 12. April 2016 gegen 01:00
* Unterstützung für Meldungen über Verlagskonten
* Wordpress-Benutzern können Name und Karteinummer zugeordnet werden
* Optionen zum Teilen von Zählmarken verbessert, Benutzer können nun entscheiden, ob sie das Teilen zulassen möchten. Das Teilen kann nun auch global vom Administrator deaktiviert werden.
* Zeige immer Benutzernamen in Zählmarkenübersicht an, sofern diese nicht dem aktuellen Benutzer zugeordnet sind
* Kleinerer Bugfix bei der Auswahl zum Gemeinsamen VG WORT-Zugang
* Zählmarken mit HTTPS werden bei der Migration berücksichtigt
* Beitrag-Tabelle sortiert automatisch absteigend nach Länge, wenn nach zu kurzen Beiträgen gefiltert wird und keine Sortierung verwendet wird
* Wordpress 4.5-kompatibilität

= 1.3 =
* Veröffentlicht: 17. Dezember 2015 gegen 13:00
* Worthy behandelt nun den Fall, dass in Wordpress ein Benutzer gelöscht wird, Zählmarken können mit den normalen Wordpress-Bordmitteln auf andere Benutzer übertragen oder als gelöscht markiert werden
* Nicht alles was so dargestellt wurde ist ein Autoren-Konflikt
* Getestet mit PHP 7.0
* Getestet mit Wordpress 4.4
* Kleinere Bugfixes bei Filtern und einem SQL-Query

= 1.2 =
* Veröffentlicht: 22. September 2015 gegen 15:00
* Premium: Anonyme Zählmarken können personalisiert und importiert werden
* Ungenutzte Zählmarken können nun exportiert werden um sie an anderer Stelle zu nutzen
* Java-Script-Erweiterung für Beitrag-Editor wurde verbessert
* Bugfix: Beitragsrevisionen erhalten keinen Zählpixel mehr
* Bugfix: CSS-Fehlerkorrektur für unser SVG-Logo

= 1.1.4 =
* Veröffentlicht: 20. August 2015 gegen 23:30
* Beschriftung in der Toolbar angepasst

= 1.1.3 =
* Veröffentlicht: 20. August 2015 gegen 22:15
* HTML-Code wurde für Wordpress 4.3 angepasst
* Über die Beitrags-Ansicht von Wordress konnten keine Zählmarken zugeordnet werden
* Ein paar mehr Texte auf Wunsch eines Nutzers hinzugefügt
* Das Plugin-Icon wurde getauscht
* Reiter sind nun auch über ein Untermenü aufrufbar
* Diverse Umstrukturierungen im Quellcode
* Nach Registrierung für Premium wird der Status automatisch erstmalig synchronisiert
* Für Worthy notwendige Berechtigungen wurden angepasst (waren vorher vollkommen falsch)
* Worthy weißt aggressiver darauf hin, wenn keine Zählmarken mehr verfügbar sind
* Untere Aktion-Dropdown funktioniert nun wieder
* Die Ausgabe von Zählmarken kann nun komplett unterbunden werden

= 1.1.2 =
* Veröffentlicht: 20. Juli 2015 gegen 15:30
* CSV-Import funktioniert nun auch für Verlagskonten
* Zählpixel können auch in Blogs verwendet werden die HTTPS nutzen (Danke an Chrisss für die Recherche)
* Wordpress-Nutzer können "zusammengeschaltet" werden
* Präferenz um automatisch Zählmarken zuzurodnen sobald ein Beitrag die Mindestlänge erreicht (Feature-Wunsch eines Nutzers)
* Die Länger der Überschrift wird im Editor der Meldungsvorschau angezeigt (Danke an Chrisss für das gemeinsame Brainstorming)
* Der Worthy Premium Shop gibt nun etwas mehr Informationen zu den verfügbaren Produkten (Feature-Wunsch eines Nutzers)

= 1.1.1 =
* Veröffentlicht: 15. Juli 2015 gegen 23 Uhr
* Kategorien- und Schlagwörter-Spalten in Beitragsansicht waren defekt (Danke an -thh für den Report)
* Vorbereitung um einzelne Autoren mit anderen zu verknüpfen

= 1.1 =
* Veröffentlicht: 15. Juli 2015 gegen 22:30
* Das Plugin kann nun mehrere Autoren parallel bedienen
* Beitrag-Tabelle weist auf zu lange Überschriften hin
* Beiträge können in Meldungs-Vorschau bearbeitet werden
* Worthy Premium Zählmarken-Synchronisation nicht unnötig oft
* Beiträge die mehr als ein Zählpixel enthalten werden markiert
* Kleinere Fehlerkorrekturen im Plugin
* Kleinere Anpassungen an der readme.txt

= 1.0 =
* Veröffentlicht: 13. Juli 2015 gegen 23 Uhr
* **Erstes öffentliches Release von Worthy**
* Import und Export von CSV-Listen mit Zählmarken von VG WORT
* Zählen von relevanten Zeichen im Beitrags-Editor
* Ignorieren von Beiträgen für Worthy
* Zuordnen von Zählmarken zu Beiträgen
* Übersicht über alle Zählmarken in der Worthy Datenbank
* Übersicht aller Beiträge mit und ohne Zählmarken mit Filter-Funktion
* Suche nach öffentlichen und privaten Zählmarken
* Zählmarken-Recherche anhand CSV-Liste aus T.O.M.
* Separater Zeichen-Index für Beiträge
* Migrations-Funktionen für eingebettete Zählmarker und die Plugins VGW (VG-Wort Krimskram), WP VG-Wort, Prosodia VGW sowie Torben Leuschners' VG-Wort

* **Premium-Features**
  * Gratis Test-Zugang für 7 Tage mit 3 Meldungen an VG WORT
  * Bestellen des Abonnements direkt über Worthy
  * Synchronisation von Zählmarken-Status
  * Bestellung von neuen Zählmarken
  * Erstellen von Webbereichen
  * Vorschau für Beitragsmeldung
  * Melden von Texten an die VG WORT
  * Recherche nach Zählmarken-Status
  * Personalisieren und Importieren von beliebig vielen anonymen Zählmarken (sofern die VG WORT dies anbietet, Stand Januar 2016 ist dies nicht mehr der Fall)

== Screenshots ==

1. Worthy: Übersicht - Auf einen Blick alle Zahlen und Fakten
2. Worthy: Zählmarken-Ansicht - Recherche zu allen Worthy bekannten Zählmarken. Mit Worthy-Premium wird auch der Status der Zählmarke angezeigt und der Beitrag lässt sich direkt an die VG WORT melden.
3. Worthy: Beitrag-Ansicht - Recherche zu allen Beiträgen in Wordpress mit maßgeschneiderten Filtern und Massenoperationen wie z.B. dem Zuordnen von Zählmarken. Mit Worthy-Premium lassen sich hier auch Texte für die Meldung oder Sondermeldung recherchieren und direkt melden.
4. Worthy: Im-/Export - Import und Export von Zählmarken, Berichte zum Melden ohne Worthy Premium sowie die Migration von anderen Plugins nach Worthy. Mit Worthy Premium könnten hier auch anonyme Zählmarken personalisiert werden (sofern von der VG WORT angeboten)
5. Worthy: Einstellungen - Viel zu Vielfältig für eine kurze Bild-Beschreibung!
6. Wordpress: Post-Übersicht - Worthy integriert sich nahtlos in die bereits bekannte Post-Übersicht und zeigt alle relevanten Daten direkt an.
7. Wordpress: Post-Editor - Alle Worthy-Einstellungen kompakt und übersichtlich. Visuelles Feedback und Benutzer-Präferenzen verhindern, dass man einmal die Zählmarke vergisst.

== Upgrade Notice ==
= Updates für Worthy =
Updates für Worthy selbst werden immer in einer Form bereitgestellt, die ein vollautomatisches Upgrade zulassen. Du wirst mit einem Versionssprung also keine Probleme bekommen.

== Frequently Asked Questions ==
= Warum sollte ich Worthy nutzen, wenn ich bereits ein anderes Plugin für VG WORT nutze? =
Wir haben sehr viel Arbeit in Worthy gesteckt um es zu einem wunderbaren Plugin für Wordpress zu machen. Die letzten zwei Jahre haben wir eng mit Autoren zusammen gearbeitet, ihnen bei der Arbeit zugeschaut, um ihre Arbeit so einfach wie möglich zu gestalten.

Worthy bietet Dir einmalige Funktionen, die es in anderen Plugins einfach nicht gibt. Zum Beispiel gibt es mit Worthy Premium ein absolutes Alleinstellungsmerkmal von Worthy, dass Dich die gesamte Arbeit mit T.O.M. über Dein Wordpress abwickeln lässt.

Auch die Recherche- und Komfort-Funktionen der freien Worthy-Version sind recht ausgereift und erlauben Dir auch ohne Premium-Abonnement jede Menge Zeit und Arbeit zu sparen.

= Welche Daten werden wohin übermittelt? =
Worthy in der kostenlosen Version übermittelt keinerlei Daten an irgendwen, für Premium ist es indes notwendig, dass hier und da Daten übermittelt werden. Diese Daten werden im wesentlichen zwischen Deinem Wordpress-Blog, dem Worthy Premium Webservice und T.O.M. von der VG WORT ausgetauscht.
Bei der Bestellung von Worthy Premium kommt noch der Zahlungsdienstleister "Paypal" zum Zuge.

Sämtliche Daten werden natürlich SSL/TLS-verschlüsselt übermittelt, sodass nach aktuellem Standard keine Unbeteiligten Zugriff auf Deine Daten erhalten können.

Alle Datails zum Umgang mit Deinen Daten findest Du in der [Datenschutzerklärung zu Worthy Premium](https://wp-worthy.de/api/privacy.de.html)

= Muss ich Worthy Premium mehrfach buchen wenn ich mehr als einen Blog habe? =
Nein! Worthy Premium richtet sich nach dem VG WORT-Benutzerkonto für das Premium gekauft wurde. Dieses Benutzerkonto kann in beliebig vielen Wordpress-Installationen mit Worthy Premium genutzt werden. Die bezahlten Leistungen gelten dann pro Benutzerkonto und nicht pro Webseite.

Im Umkehrschluss gilt aber auch: Wollen auf einem Blog mehrere Benutzer Worthy Premium nutzen muss jeder Benutzer (der möchte) ein Worthy Premium Abonnement abschließen. Es ist ebenfalls möglich in einem Blog beliebig viele Premium- und nicht-Premium-Benutzer zu haben.

= Warum kostet Worthy Premium Geld? =
Zunächst: An diesem Projekt sind Autoren und Software-Entwickler beteiligt.
Wir haben sehr viel Arbeit in Worthy investiert und jede Mühe verdient ihren Lohn. Du wirst mit Worthy sehr viel einfacher Deine etwaigen Ansprüche auf einen Anteil vom großen Kuchen der VG WORT realisieren können und möglicherweise richtig Geld damit verdienen können. Das sollte Dir einfach eine Kleinigkeit wert sein. Was vorher Wochen dauerte, kann nun in Minuten erledigt werden.

Worthy Premium ist keine Maschine, die von alleine läuft. Als WordPress-Anwender weißt Du, wie oft da Updates kommen. Damit Worthy immer gleichbleibend zuverlässig und reibungslos funktioniert, müssen wir ständig daran arbeiten.  
Für die Pflege der Software bringen wir viel Zeit, Liebe und Aufmerksamkeit auf, die Du durch den geringen Premium-Beitrag honorieren kannst.

Aber wir wollen gar nicht, dass Du sofort Geld für Worthy bezahlst! Wir sind so von Worthy überzeugt, daß wir Dir einen kostenlosen Zugang zu den Premium-Funktionen schenken! Zeitlich und mengenmäßig begrenzt hast Du so die Möglichkeit Worthy Premium absolut kostenlos und ohne jede Verpflichtung auf Herz und Nieren zu testen. Probiere es einfach aus und dann lasse Dein Bauchgefühl darüber entscheiden, ob Worth Premium nützlich für Dich ist.

= Ich habe ein Problem mit Worthy =
Das sollte nicht sein! Worthy ist dazu da, um Dir das Leben zu erleichtern und nicht den Tag zu vermiesen. Trotzdem kann es natürlich mal passieren, dass etwas nicht so funktioniert, wie es sollte.

Lass es uns einfach im Support-Forum wissen, wir versuchen uns so schnell wie möglich darum zu kümmern.

= Ich vermisse eine Funktion XY in Worthy =
Super, sag uns einfach Bescheid, wir schauen, was wir tun können!

Wir freuen uns über Deinen Beitrag im Support-Forum.
