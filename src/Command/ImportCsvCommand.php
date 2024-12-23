<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class ImportCsvCommand extends Command
{
    private $entityManager;
    private $productRepository;
    private $filesystem;

    public function __construct(EntityManagerInterface $entityManager, ProductRepository $productRepository)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->productRepository = $productRepository;
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:import-csv')
            ->setDescription('Importe un fichier CSV dans la base de données');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

      
        $directory = __DIR__ . '/../../csv_files'; 

       
        if (!$this->filesystem->exists($directory)) {
            $io->error("Le dossier spécifié n'existe pas : $directory");
            return Command::FAILURE;
        }

       
        $csvFiles = glob($directory . '/*.csv');
        if (empty($csvFiles)) {
            $io->error("Aucun fichier CSV trouvé dans le dossier.");
            return Command::FAILURE;
        }

        
        foreach ($csvFiles as $csvFile) {
            $io->note("Importation du fichier CSV : $csvFile");
            $this->importCsvFile($csvFile, $io);
        }

        $io->success('Importation des fichiers CSV terminée.');
        return Command::SUCCESS;
    }

    private function importCsvFile(string $csvFile, SymfonyStyle $io): void
    {
        // Ouvrir le fichier CSV
        if (($handle = fopen($csvFile, 'r')) !== false) {
            $header = fgetcsv($handle); // Lire l'en-tête

            // Parcourir chaque ligne du fichier CSV
            while (($data = fgetcsv($handle)) !== false) {
                // Récupérer les valeurs des colonnes : référence, désignation, quantités, prix
                $reference = $data[0];
                $designation = $data[1];
                $quantite = (int) $data[2];
                $prix = (float) $data[3];

                // Vérifier si le produit existe déjà
                $product = $this->productRepository->findOneBy(['reference' => $reference]);

                if ($product) {
                 
                    $product->setDesignation($designation);
                    $product->setQuantite($quantite);
                    $product->setPrix($prix);
                    $io->note("Produit mis à jour : $reference");
                } else {
                  
                    $product = new Product();
                    $product->setReference($reference);
                    $product->setDesignation($designation);
                    $product->setQuantite($quantite);
                    $product->setPrix($prix);
                    $this->entityManager->persist($product);
                    $io->note("Nouveau produit ajouté : $reference");
                }
            }

            fclose($handle);
            $this->entityManager->flush(); // Enregistrer les changements dans la base de données
        }
    }
}
