��    S      �  q   L        ?     �   Q     �     �  ,        3     9  a   X  (   �  �   �     �	     �	  �   �	  �   �
  `  2     �     �     �     �     �     �     �          (     :     P     o     �     �  N   �     �          #  :  /  Z   j  ,   �      �          -  m   C  p   �     "     0     C     U  �   p  �   �  i   �  q   	  ~   {  �   �  ~   �  �     c   �     %  t   D  |   �  /   6    f     }  -   �  !   �  S   �  3   A  a   u  |   �  1   T  d   �  V   �  F   B  B   �  �  �     �     �     �     �     �     �     �  $   �  3   "     V  s  k  A   �  �   !   $   �   
   !  9   $!     ^!  -   g!  w   �!  5   "  �   C"  
   .#  #   9#    ]#  �   m$  �  7%     '     '     :'     W'     m'     �'  !   �'     �'     �'  *   �'     *(     J(     j(     �(  m   �(  !   )     ))  
   H)  �  S)  z   �*  P   \+  ?   �+  )   �+     ,  �   7,  �   �,     w-     �-     �-  3   �-  �   �-  �   l.  �   �.  �   �/  �   &0  �   �0  �   G1  �   �1  �   z2  0   	3  �   :3  �   �3  <   4  g  �4  &   $6  ,   K6  )   x6  i   �6  ;   7  l   H7  �   �7  :   >8  �   y8  o   9  R   �9  K   �9    (:  ,   @<     m<     �<     �<     �<     �<     �<  +   �<  :   =  "   C=     ;   I       6   G            %           7   	      S   2   '   L      D   4                  M   "   (       -      !   =   &                :   N   ,          3       @       /   E   9       B      5       H                            O   #   ?       1   .   C   F          +   >      R          
           K   P             )   Q                     J              $                <       *          A   8      0    A compilation error was detected in the following export filter A custom module to download GEDCOM files on URL requests with the tree name, GEDCOM file name, and authorization provided as parameters within the URL. Action not accepted Activate Activate encryption of the authorization key Allow Allow download of GEDCOM files Any parameters provided in the URL have a higher priority and will overrule the default settings. Both, i.e. download and save in parallel By un-selecting this option, it is possible to de-activate downloads. This setting might be used if GEDCOM files shall only be saved on the server, but not downloaded. Control panel Current authorization key Currently, the authorization key is not encrypted. This option is less secure and should only be used in local environments with limited users. Otherwise, please activate encrpytion of the authorization key. Currently, the download of GEDCOM files is allowed. Please note that everyone with access to the authorization key, can download GEDCOM files from your webtrees installation. Currently, the folder to save is not a sub-directory of the webtrees data folder. It is highly recommended to use the webtrees data folder or a sub-directory, because webtrees protects unauthorized access to this folder. If you choose a folder outside of the webtrees data folder, the saved GEDCOM file might be unprotected against unauthorized access. Custom module Default GEDCOM 7 flag Default GEDCOM-L flag Default action Default encoding Default ending Default export filter Default export format Default file name Default privacy level Default settings for downloads Default time stamp Default tree Do not use GEDCOM-L Download is not allowed. Please change the module settings to allow downloads. Encoding not accepted Export format not accepted Folder name Folder within the webtrees root path, where GEDCOM exports are saved. It is highly recommended to use the webtrees data folder or a sub-directory, because webtrees protects unauthorized access to the data folder. The current settings (Control panel / Website preferences) for the webtrees root and data folder are: In order to use changed settings for a test download, the settings need to be saved first. Key (encrypted) not accepted. Access denied. Key not accepted. Access denied. Line endings not accepted New authorization key No key provided. For checking of the access rights, it is mandatory to provide a key as parameter in the URL. No secret key defined. Please define secret key in the module settings: Control Panel / Modules / All Modules /  No time stamp Postfix time stamp Prefix time stamp Privacy level not accepted Select the default GEDCOM version. This GEDCOM version will be chosen if no specific GEDCOM version is provided as URL parameter. Select the default action. This action will be chosen if no specific action is provided as URL parameter. If "both" is chosen, the file is downloaded and saved in parallel. Select the default ending. This ending will be chosen if no specific ending is provided as URL parameter. Select the default export filter. This  filter will be chosen if no specific filter is provided as URL parameter. Select the default export format. This export format will be chosen if no specific export format is provided as URL parameter. Select the default for GEDCOM-L usage. This GEDCOM-L setting will be chosen for GEDCOM 7.0 exports if no specific GEDCOM-L setting is provided as URL parameter. Select the default privacy level. This privacy level will be chosen if no specific privacy level is provided as URL parameter. Select the default time stamp. This time stamp will be chosen if no specific time stamp is provided as URL parameter. If "none" is chosen, no time stamp will be used. Select the default tree. This tree will be chosen if no specific tree is provided as URL parameter. Settings for authorization key Specifiy the default file name. This file name will be chosen if no specific file name is provided as URL parameter. The authorization key cannot be shown, because encryption is activated. If you forgot the key, you have to create a new key. The authorization key is empty or not available The encryption of the authorization key is more secure, because the authorization key is not visible to anyone and also encrypted in the database. However, the authorization key is not readible any more (e.g. for other administrators) and cannot be recovered if it is forgotten. The export filter was not found The family tree "%s" has been exported to: %s The file %s could not be created. The folder settings could not be saved, because the folder “%s” does not exist. The following GEDCOM structure could not be matched The preferences for the custom module "%s" were sucessfully updated to the new module version %s. The preferences for the default export filter were reset to "none", because the selected export filter %s could not be found The preferences for the module "%s" were updated. The provided secret key contains characters, which are not accepted. Please provide a different key. The provided secret key is too short. Please provide a minimum length of 8 characters. The selected export filter (%s) contains an invalid regular expression The selected export filter (%s) contains an invalid tag definition These default settings are used if no specific parameter values are provided within the URL. By specifying the default values, the URLs to be called for a download can be simplified. If the default values shall be used for a download, it is sufficient to only provide the "key" parameter (authorization key) in the URL. Please note that the default settings can only be used after saving at least once (i.e. by pressing the "Save" button). Time stamp setting not accepted Tree not found Use GEDCOM-L error message includes media files line webtrees data folder webtrees data folder (relative path) webtrees data folder (setting in the control panel) webtrees root folder Project-Id-Version: DownloadGedcomWithURL
PO-Revision-Date: 2024-05-24 18:10+0200
Last-Translator: 
Language-Team: 
Language: de
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=(n != 1);
X-Generator: Poedit 3.4.4
X-Poedit-Basepath: ../..
X-Poedit-KeywordsList: translate
X-Poedit-SearchPath-0: .
 In folgendem Export-Filter wurde ein Kompilierungs-Fehler erkannt Ein benutzerdefiniertes Modul zum Herunterladen von GEDCOM Dateien auf Basis von URL Anfragen, welche den Stammbaum-Namen, den GEDCOM Dateinamen und die Authorisierung als Paramter innerhalb der URL beinhalten. Angeforderte Aktion nicht akzeptiert Aktivieren Verschlüsselung des Autorisierungs-Schlüssel aktivieren Erlauben Das Herunterladen von GEDCOM Dateien erlauben Parameter, welche über die URL übergeben werden, haben eine höhere Priorität und überstimmen die Voreinstellungen. Beides, d.h. Herunterladen und gleichzeitig Speichern Durch Abwahl dieser Einstellung ist es möglich, das Herunterladen zu deaktivieren. Diese Einstellung kann genutzt werden, wenn GEDCOM Dateien nur auf dem Server abgespeichert werden sollen, jedoch nicht heruntergeladen werden sollen. Verwaltung Aktueller Autorisierungs-Schlüssel Aktuell ist der Autorisierungs-Schlüssel nicht verschlüsselt. Diese Einstellung ist weniger sicher und sollte nur in einer lokalen Umgebung mit begrenzten Benutzern verwendet werden. Andernfalls, aktivieren sie bitte die Verschlüsselung des Autorisierungs-Schlüssels. Aktuell ist das Herunterladen von GEDCOM Dateien erlaubt. Bitte beachten Sie, dass jeder mit Zugriff auf den Autorisierungs-Schlüssel GEDCOM-Dateien von Ihrer webtrees Installation herunterladen kann. Aktuell ist der Ordner zum Abspeichern von GEDCOM Dateien kein Unterverzechnis des Webtrees Datenverzeichnisses. Es wird dringend empfohlen, das Webtrees Datenverzeichnis oder ein Unterverzeichnis zu nutzen, weil Webtrees das Datenverzeichnis gegen unauthorisierten Zugriff absichert. Wenn Sie einen Ordner außerhalb des Webtrees Datenverzeichnisses wählen, sind abgespeicherte GEDCOM-Dateien möglicherweise nicht gegen unauthorisierten Zugriff geschützt. Benutzerdefiniertes Modul Voreinstellung GEDCOM 7 Flag Voreinstellung GEDCOM-L Flag Voreinstellung Aktion Voreinstellung Zeichencodierung Voreinstellung Zeilenenden Voreinstellung für Export-Filter Voreinstellung Exportformat Voreinstellung Dateiname Voreinstellung für Datenschutzeinstellung Voreinstellungen für Downloads Voreinstellung für Zeitstempel Voreinstellung Stammbaum GEDCOM-L nicht nutzen Das Herunterladen ist nicht erlaubt. Bitte ändern Sie die Modul-Einstellungen, um Herunterladen zu erlauben. Zeichencodierung nicht akzeptiert Export-Format nicht akzeptiert Ordnername Ordner innerhalb des Webtrees Installations-Pfads, in welchem GEDCOM-Exporte abgespeichert werden. Es wird dringend empfohlen, das Webtrees Datenverzeichnis oder ein Unterverzeichnis zu nutzen, weil Webtrees das Datenverzeichnis gegen unauthorisierten Zugriff absichert. Die aktuellen Einstellungen (Verwaltung / Einstellungen Webseite) für das Webtrees Installations- und Daten-Verzeichnis sind: Um geänderte Einstellungen für "Herunterladen testen" zu nutzen, müssen die Einstellungen zunächst gespeichert werden. Autorisierungs-Schlüssel (verschlüsselt) nicht akzeptiert. Zugriff verweigert. Autorisierungs-Schlüssel nicht akzeptiert. Zugriff verweigert. Angabe bzgl. Zeilenenden nicht akzeptiert Neuer Autorisierungs-Schlüssel Kein Autorisierungs-Schlüssel übergeben. Zum Überprüfen der Zugriffsrechte, ist die Angabe eines Autorisierungs-Schlüssels als URL-Parameter erforderlich. Kein Autorisierungs-Schlüssel definiert. Bitte definieren Sie einen Autorisierungs-Schlüssel in den Modul-Einstellungen: Verwaltung / Module / Alle Module /  Kein Zeitstempel Postfix Zeitstempel Prefix Zeitstempel Wert für Datenschutzeinstellungen nicht akzeptiert Geben Sie die Voreinstellung für das GEDCOM 7 Flag an. Dieser Wert wird genutzt, wenn kein spezifischer Wert in der URL angegeben wird. Geben Sie die Voreinstellung für die Aktion an. Diese Aktion wird durchgeführt, wenn keine spezifische Aktion in der URL angegeben wird. Geben Sie die Voreinstellung für die Zeilenenden an. Diese Zeilenenden werden genutzt, wenn keine spezifischen Zeilenenden in der URL angegeben werden.. Geben Sie die Voreinstellung für den Export-Filter an. Dieser Filter wird genutzt, wenn kein spezifischer Filter als URL-Parameter übergeben wird. Geben Sie die Voreinstellung für das Exportformat an. Dieses Exportformat wird genutzt, wenn kein spezifisches Exportformat in der URL angegeben wird. Geben Sie die Voreinstellung für das GEDCOM-L Flag an. Dieser Wert wird genutzt, wenn kein spezifischer Wert in der URL angegeben wird. Geben Sie die Voreinstellung für die Datenschutzeinstellung an. Diese Einstellung wird genutzt, wenn keine spezifische Einstellung in der URL angegeben wird. Geben Sie die Voreinstellung für den Zeitstempel an. Diese Einstellung wird genutzt, wenn keine spezifische Einstellung in der URL angegeben wird. Geben Sie die Voreinstellung für den Stammbaum an. Dieser Stammbaum wird genutzt, wenn kein spezifischer Stammbaum in der URL angegeben wird. Einstellungen für den Autorisierungs-Schlüssel Geben Sie die Voreinstellung für den Dateinamen an. Dieser Dateinamen wird genutzt, wenn kein spezifischer Dateinamen über die URL bereitgestellt wird. Der Autorisierungs-Schlüssel kann nicht angezeigt werden, weil Verschlüsselung aktiviert ist. Wenn Sie den Schlüssel vergessen haben, müssen Sie einen neuen erzeugen. Der Autorisierungs-Schlüssel ist leer oder nicht verfügbar Die Verschlüsselung des Autorisierungs-Schlüssels ist sicherer, weil der Autorisierungs-Schlüssel nicht mehr sichtbar ist und auch in der Datenbank verschlüsselt wird. Allerdings ist der Autorisierungs-Schüssel in diesem Fall nicht mehr lesbar (z.B. für andere Administratoren) und kann auch nicht mehr wiederhergestellt werden, wenn er vergessen wurde. Der Export-Filter wurde nicht gefunden Der Stammbaum "%s" wurde exportiert nach: %s Die Datei %s konnte nicht erzeugt werden. Die Einstellungen für den Ordner konnten nicht gespeichert werden, weil der Ordner "%s" nicht existiert. Die folgende GEDCOM-Struktur konnte nicht zugeordnet werden Die Einstellungen für das benutzerdefinierte Modul "%s" wurden für die neue Modul-Version %s aktualisiert. Die Voreinstellung für den Export-Filter wurden auf "Keine" zurückgesetzt, weil der ausgewählte Export-Filter %s nicht gefunden wurde Die Einstellungen für das Modul "%s" wurden aktualisiert. Der eingegebene Autorisierungs-Schlüssel enthält Schriftzeichen, welche nicht akzeptiert werden. Bitte geben Sie einen anderen Autorisierungs-Schlüssel ein. Der angegebene Autorisierungs-Schlüssel ist zu kurz. Bitte geben Sie mindestens eine Länge von 8 Zeichen ein. Der ausgewählte Export-Filter (%s) enthält einen ungültigen regulären Ausdruck Der ausgewählte Export-Filter (%s) enthält eine ungültige Tag-Definition Diese Einstellungen werden genutzt, wenn keine speziellen Parameter über die URL übergeben werden. Durch Festlegung dieser Einstellungen können die URLs zum Herunterladen vereinfacht werden. Wenn die Voreinstellungen für das Herunterladen genutzt werden sollen, reicht es aus, einfach nur den "key" Parameter (Authorisierungs-Schlüssel) in der URL zu übergeben. Bitte beachte Sie, dass die Voreinstellungen nur genutzt werden können, nachdem sie zumindest einmal gespeichert wurden (d.h. durch Drücken des "Speichern" Buttons). Einstellung zum Zeitstempel nicht akzeptiert Stammbaum nicht gefunden GEDCOM-L nutzen Fehlermeldung enthält Mediendateien Zeile Webtrees Daten-Verzeichnis Webtrees Daten-Verzeichnis (relativer Pfad) Webtrees Daten-Verzeichnis (Einstellung in der Verwaltung) Webtrees Installations-Verzeichnis 