<?php

namespace App\DataPersister;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use App\Entity\Contact;
use App\Service\MessageGenerator;
use App\Entity\FicheDePaie;
use App\Repository\DeductionRepository;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\PrimeRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Knp\Bundle\SnappyBundle\Snappy;
use Twig\Environment;
use Knp\Snappy\Pdf;

class FicheDePaieDataPersister implements DataPersisterInterface
{
    private $entityManager;
    private $twig;
    private $deductionRepository;
    private $primeRepository;


    public function __construct(EntityManagerInterface $entityManager,DeductionRepository $deductionRepository,PrimeRepository $primeRepository,Environment $environment)
    {
        $this->entityManager = $entityManager;
        $this->deductionRepository = $deductionRepository;
        $this->primeRepository = $primeRepository;
        $this->twig = $environment;



    }

    public function supports($data):bool
    {
        return $data instanceof FicheDePaie;
    }

    /**
     * @param FicheDePaie $data
     */
    public function persist($data)
    {
       
        $contact=$data->getContact()->getId();
        $year=$data->getYear();
        $month=$data->getMonth();
        $sommededuction=$this->deductionRepository->deductionbyyear($year,$contact);
        $sommededuction=$sommededuction['total'];
        $sommeprime=$this->primeRepository->primebyyearbymonth($year,$month,$contact);
        $sommeprime=$sommeprime['total'];
        $salairebase=$data->getContact()->getSalaireDeBase();
        $salairebrute=$salairebase+$sommeprime;
        $salaireimposable=$salairebrute-($salairebrute*0.0918);
        $x1=$salaireimposable*0.90;
        $x2=$x1*12;
        $x3=$x2-$sommededuction;
        $resulterpp=0;
        $resultcss=0;
        if($x3>=0 && $x3<=5000){
            $resulterpp=0;
            $resultcss=$x3*0.01;

        }elseif($x3>=5000 && $x3<=20000){
            $errp0=0;
            $css0=5000*0.01;
            $resulterpp=($x3-5000)*0.26+$errp0;
            $resultcss=($x3-5000)*0.27+$css0;

        }
        elseif($x3>=20000 && $x3<=30000){
            $errp0=0;
            $css0=5000*0.01;
            $errp1=15000*0.26;
            $css1=15000*0.27;
            $resulterpp=($x3-20000)*0.28+$errp0+$errp1;
            $resultcss=($x3-20000)*0.29+$css0+$css1;

        }
        elseif($x3>=30000 && $x3<=50000){
            $errp0=0;
            $css0=5000*0.01;
            $errp1=15000*0.26;
            $css1=15000*0.27;
            $errp2=10000*0.28;
            $css2=10000*0.29;
            $resulterpp=($x3-30000)*0.32+$errp0+$errp1+$errp2;
            $resultcss=($x3-30000)*0.33+$css0+$css1+$css2;

        }
        elseif($x3>50000){
            $errp0=0;
            $css0=5000*0.01;
            $errp1=15000*0.26;
            $css1=15000*0.27;
            $errp2=10000*0.28;
            $css2=10000*0.29;
            $errp3=20000*0.32;
            $css3=20000*0.33;
            $resulterpp=($x3-50000)*0.35+$errp0+$errp1+$errp2+$errp3;
            $resultcss=($x3-50000)*0.36+$css0+$css1+$css2+$css3;
        }
        
        $erpp=$resulterpp/12;
        $css=($resultcss/12)-$erpp;
        $salairenet=$salaireimposable-$erpp-$css;
        $data->setSalaireNet($salairenet);
        $data->setCss($css);
        $data->setErpp($erpp);
        $data->setPrime($sommeprime);
        $this->entityManager->persist($data);
        $this->entityManager->flush();
        $pdf=new Pdf("wkhtmltopdf.exe",[],[]);
        $html = 'fp/new.html.twig';
        $raisonsociale=$data->getContact()->getEntreprise()->getRaisonSociale();
        $numcnsssociete=$data->getContact()->getEntreprise()->getNumeroCnss();
        $matricule=$data->getContact()->getMatricule();
        $nom=$data->getContact()->getName();
        $surname=$data->getContact()->getSurname();
        $categorie=$data->getContact()->getCategorie();
        $echelon=$data->getContact()->getEcheleon();
        $ncnsscontact=$data->getContact()->getNumCnss();
        $enfant=$data->getContact()->getNombreEnfant();
        $path="C:\Users\wadi3\Desktop\lesfiches\{$nom}Fiche-De-Paie.pdf";
      

            $pdf->generateFromHtml(
                $this->twig->render(
                    $html,
                    [
                        "raisonsociale" => $raisonsociale,
                        "numcnsssociete"=>$numcnsssociete,
                        "matricule"=>$matricule,
                        "nom"=>$nom,
                        "surname"=>$surname,
                        "categorie"=>$categorie,
                        "echelon"=>$echelon,
                        "ncnsscontact"=>$ncnsscontact,
                        "enfant"=>$enfant,
                        "salairebase"=>$salairebase,
                        "salairebrut"=>$salairebrute,
                        "salaireimposable"=>$salaireimposable,
                        "sommeprime"=>$sommeprime,
                        "retenue"=>$salairebrute*0.0918,
                        "erpp"=>$erpp,
                        "css"=>$css,
                        "month"=>$month,
                        "year"=>$year,



                    ]
                ),
                $path 
            
            );
    
     
    }

    public function remove($data)
    {
        $this->entityManager->remove($data);
        $this->entityManager->flush();
    }
}