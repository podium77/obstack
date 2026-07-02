<?php

namespace App\Service\DatabaseConnector;

/**
 * Interface pour tous les connecteurs de bases de données.
 * 
 * Chaque implémentation doit supporter les opérations
 * de gestion de base de données pour un type de moteur spécifique.
 */
interface DatabaseConnectorInterface
{
    /**
     * Établir une connexion à la base de données.
     * 
     * @throws \RuntimeException Si la connexion échoue
     */
    public function connect(): void;

    /**
     * Fermer la connexion à la base de données.
     */
    public function disconnect(): void;

    /**
     * Tester si la connexion est fonctionnelle.
     * 
     * @return bool True si la connexion est OK, false sinon
     */
    public function testConnection(): bool;

    /**
     * Lister les structures principales (schémas, collections, etc).
     *  
     * @return array Structures disponibles
     */
    public function listStructures(): array;

    /**
     * Lister les données d'une structure.
     * 
     * @param string $structure Nom de la structure (table, collection, etc)
     * @param array $options Options de pagination/filtrage
     * 
     * @return array Données paginées
     */
    public function listData(string $structure, array $options = []): array;

    /**
     * Insérer des données.
     * 
     * @param string $structure Cible (table, collection, etc)
     * @param array $data Données à insérer
     * 
     * @return mixed ID ou identifiant créé
     */
    public function insert(string $structure, array $data): mixed;

    /**
     * Mettre à jour des données.
     * 
     * @param string $structure Cible
     * @param array $criteria Conditions
     * @param array $data Nouvelles valeurs
     * 
     * @return int Nombre de lignes/documents modifiés
     */
    public function update(string $structure, array $criteria, array $data): int;

    /**
     * Supprimer des données.
     * 
     * @param string $structure Cible
     * @param array $criteria Conditions de suppression
     * 
     * @return int Nombre de lignes/documents supprimés
     */
    public function delete(string $structure, array $criteria): int;

    /**
     * Exécuter une requête personnalisée (pour admin uniquement).
     * 
     * @param string $query Requête native
     * @param array $params Paramètres
     * 
     * @return mixed Résultat brut
     */
    public function executeQuery(string $query, array $params = []): mixed;

    /**
     * Obtenir le type de moteur.
     * 
     * @return string mysql|postgresql|neo4j|arangodb
     */
    public function getType(): string;

    /**
     * Vérifier si le connecteur est connecté.
     * 
     * @return bool
     */
    public function isConnected(): bool;
}
