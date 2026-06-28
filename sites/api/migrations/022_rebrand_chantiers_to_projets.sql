-- Rebranding Chantiers to Projets
-- Update devis table
ALTER TABLE devis CHANGE chantier_id projet_id INT(11) NULL;

-- Rename tables
RENAME TABLE chantiers TO projets;
RENAME TABLE chantier_medias TO projet_medias;
RENAME TABLE chantier_stories TO projet_stories;
RENAME TABLE chantier_story_medias TO projet_story_medias;

-- Update columns within these specific tables
ALTER TABLE projet_medias CHANGE chantier_id projet_id INT(11) NOT NULL;
ALTER TABLE projet_stories CHANGE chantier_id projet_id INT(11) NOT NULL;

-- Update other references
ALTER TABLE site_story_feeds CHANGE chantier_id projet_id INT(11) NULL;
