<?php

namespace App\Command;

use App\Entity\ProductColor;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-colors',
    description: 'Convertit les couleurs CSV existantes en entités ProductColor',
)]
class MigrateColorsCommand extends Command
{
    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $products = $this->productRepository->findAll();
        $count = 0;

        foreach ($products as $product) {
            if (!$product->getColors()) {
                continue;
            }

            $colorNames = array_map('trim', explode(',', $product->getColors()));

            foreach ($colorNames as $colorName) {
                if ($colorName === '') {
                    continue;
                }

                $productColor = new ProductColor();
                $productColor->setName($colorName);
                $productColor->setProduct($product);
                $this->entityManager->persist($productColor);
                $count++;
            }

            $io->writeln('Migré : ' . $product->getName() . ' (' . count($colorNames) . ' couleurs)');
        }

        $this->entityManager->flush();

        $io->success($count . ' couleur(s) migrée(s) avec succès.');

        return Command::SUCCESS;
    }
}