<?php
function afficherDateComplete($timestamp = null)
{
    $jours_fr = [
        0 => 'Dimanche',
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi'
    ];

    $mois_fr = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre'
    ];

    $timestamp = $timestamp ?? time();

    $jour = $jours_fr[date('w', $timestamp)];
    $numero_jour = date('j', $timestamp);
    $mois = strtolower($mois_fr[date('n', $timestamp)]);
    $annee = date('Y', $timestamp);

    return "$jour $numero_jour $mois $annee";
}
?>