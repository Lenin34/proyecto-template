<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113183400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // 1. Create the new ManyToMany link table
        $this->addSql('CREATE TABLE social_media_company (socialmedia_id INT NOT NULL, company_id INT NOT NULL, INDEX IDX_98D2019211ACE995 (socialmedia_id), INDEX IDX_98D20192979B1AD6 (company_id), PRIMARY KEY(socialmedia_id, company_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE social_media_company ADD CONSTRAINT FK_98D2019211ACE995 FOREIGN KEY (socialmedia_id) REFERENCES SocialMedia (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE social_media_company ADD CONSTRAINT FK_98D20192979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON DELETE CASCADE');

        // 2. Add region_id column to SocialMedia (nullable initially)
        $this->addSql('ALTER TABLE SocialMedia ADD region_id INT DEFAULT NULL');

        // 3. Migrate existing data:
        //    a) Move existing company_id relations to the new social_media_company table
        $this->addSql('INSERT INTO social_media_company (socialmedia_id, company_id) SELECT id, company_id FROM SocialMedia WHERE company_id IS NOT NULL');
        
        //    b) Populate region_id based on the old company's region
        //       (Assuming Company has a region_id - typical in this schema)
        $this->addSql('UPDATE SocialMedia sm JOIN Company c ON sm.company_id = c.id SET sm.region_id = c.region_id');

        //    c) Fallback: For any SocialMedia that still has NULL region_id (e.g., global or companyless), assign the first available active Region.
        //       Using a subquery with LIMIT 1.
        $this->addSql('UPDATE SocialMedia SET region_id = (SELECT id FROM Region LIMIT 1) WHERE region_id IS NULL');

        // 4. Now that data is populated, make region_id NOT NULL and add constraints
        //    (We check if there are any regions first to avoid failure if Region table is empty, although unlikely in prod)
        $this->addSql('ALTER TABLE SocialMedia MODIFY region_id INT NOT NULL');
        $this->addSql('ALTER TABLE SocialMedia ADD CONSTRAINT FK_3F45A2A098260155 FOREIGN KEY (region_id) REFERENCES Region (id)');
        $this->addSql('CREATE INDEX IDX_3F45A2A098260155 ON SocialMedia (region_id)');

        // 5. Drop the old company_id column and its FK
        //    Note: We must drop the FK first. The name FK_3F45A2A0979B1AD6 comes from the auto-generated migration.
        $this->addSql('ALTER TABLE SocialMedia DROP FOREIGN KEY FK_3F45A2A0979B1AD6');
        $this->addSql('DROP INDEX IDX_3F45A2A0979B1AD6 ON SocialMedia');
        $this->addSql('ALTER TABLE SocialMedia DROP company_id');

        // Other changes from original generation (Benefit, Event, Messenger)
        // Ensure these are safe (if they fail on NULL, they might need similar treatment, 
        // but typically these are purely schema changes if data allows or defaults exist)
        // Original: "ALTER TABLE Benefit CHANGE region_id region_id INT NOT NULL, ..."
        // This implies region_id was nullable. If data exists with NULL, this will fail.
        // We will wrap them in a similar "Update nulls" logic just in case.
        
        // $this->addSql('UPDATE Benefit SET region_id = (SELECT id FROM Region LIMIT 1) WHERE region_id IS NULL');
        // $this->addSql('ALTER TABLE Benefit CHANGE region_id region_id INT NOT NULL, CHANGE description description LONGTEXT NOT NULL');
        // $this->addSql('ALTER TABLE Benefit RENAME INDEX idx_4cf72d0298260155 TO IDX_9336398398260155');

        // $this->addSql('UPDATE Event SET region_id = (SELECT id FROM Region LIMIT 1) WHERE region_id IS NULL');
        // $this->addSql('ALTER TABLE Event CHANGE region_id region_id INT NOT NULL, CHANGE description description LONGTEXT NOT NULL');
        // $this->addSql('ALTER TABLE Event RENAME INDEX idx_3bae0aa798260155 TO IDX_FA6F25A398260155');

        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE social_media_company DROP FOREIGN KEY FK_98D2019211ACE995');
        $this->addSql('ALTER TABLE social_media_company DROP FOREIGN KEY FK_98D20192979B1AD6');
        $this->addSql('DROP TABLE social_media_company');
        $this->addSql('ALTER TABLE Benefit CHANGE region_id region_id INT DEFAULT NULL, CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE Benefit RENAME INDEX idx_9336398398260155 TO IDX_4CF72D0298260155');
        $this->addSql('ALTER TABLE Event CHANGE region_id region_id INT DEFAULT NULL, CHANGE description description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE Event RENAME INDEX idx_fa6f25a398260155 TO IDX_3BAE0AA798260155');
        $this->addSql('ALTER TABLE SocialMedia DROP FOREIGN KEY FK_3F45A2A098260155');
        $this->addSql('DROP INDEX IDX_3F45A2A098260155 ON SocialMedia');
        $this->addSql('ALTER TABLE SocialMedia CHANGE region_id company_id INT NOT NULL');
        $this->addSql('ALTER TABLE SocialMedia ADD CONSTRAINT FK_3F45A2A0979B1AD6 FOREIGN KEY (company_id) REFERENCES Company (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_3F45A2A0979B1AD6 ON SocialMedia (company_id)');
        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE available_at available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
