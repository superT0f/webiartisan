-- Ajout du type 'tabac' aux POI locaux
ALTER TABLE local_pois
MODIFY COLUMN type ENUM(
    'mairie','piscine','bibliotheque','mediatheque','cinema','dechetterie','poste',
    'supermarche','transport','ecole','college','lycee','hopital','clinique','pharmacie',
    'eglise','monument','parc','sport','tabac','autre'
) NOT NULL;
