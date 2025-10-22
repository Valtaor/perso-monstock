-- phpMyAdmin SQL Dump
-- version 4.9.11
-- https://www.phpmyadmin.net/
--
-- Hôte : db5000543572.hosting-data.io
-- Généré le : ven. 17 oct. 2025 à 10:27
-- Version du serveur : 5.7.42-log
-- Version de PHP : 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `dbs521868`
--

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `description`) VALUES
(1, 'Colliers', ''),
(2, 'Boucles d’oreilles', ''),
(3, 'Bracelets', ''),
(4, 'Bagues', ''),
(5, 'Objets vintage', ''),
(6, 'Affiches', ''),
(7, 'Cendriers', ''),
(8, 'Figurines', '');

-- --------------------------------------------------------

--
-- Structure de la table `import_temp`
--

CREATE TABLE `import_temp` (
  `reference` varchar(50) DEFAULT NULL,
  `nom` varchar(255) DEFAULT NULL,
  `description` text,
  `categorie` varchar(100) DEFAULT NULL,
  `prix_achat` decimal(10,2) DEFAULT NULL,
  `prix_vente` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_stock`
--

CREATE TABLE `mouvements_stock` (
  `id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `type` enum('entrée','sortie','ajustement') NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) DEFAULT NULL,
  `prix_total` decimal(10,2) DEFAULT '0.00',
  `auteur` varchar(100) DEFAULT NULL,
  `commentaire` varchar(255) DEFAULT NULL,
  `date_mouvement` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `mouvements_stock`
--

INSERT INTO `mouvements_stock` (`id`, `produit_id`, `type`, `quantite`, `prix_unitaire`, `prix_total`, `auteur`, `commentaire`, `date_mouvement`) VALUES
(1, 2, 'entrée', 0, NULL, '0.00', 'bellafrance.fr', 'Ajout de produit dans le stock', '2025-10-13 08:32:55');

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

CREATE TABLE `produits` (
  `id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `description` text,
  `categorie` varchar(100) DEFAULT NULL,
  `prix_achat` decimal(10,2) DEFAULT NULL,
  `prix_vente` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT '0',
  `date_ajout` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `categorie_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `ajoute_par` varchar(100) DEFAULT NULL,
  `derniere_modif` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `a_completer` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id`, `reference`, `nom`, `description`, `categorie`, `prix_achat`, `prix_vente`, `stock`, `date_ajout`, `categorie_id`, `image`, `ajoute_par`, `derniere_modif`, `a_completer`) VALUES
(1, 'CC15', 'Collier pendentif doré multi-cordons', '', 'Colliers', '0.00', '0.00', 0, '2025-10-11 09:17:42', NULL, '/wp-content/uploads/inventaire/Collier pendentif doré multi-cordons noirs-cc15-img8.jpg', 'bellafrance.fr', '2025-10-12 05:46:25', 0),
(2, 'CC41', 'Collier feuille ajourée', '', NULL, '1.93', '0.00', 0, '2025-10-13 08:32:55', NULL, 'inv_68ecb9379d6ae7.94557150.jpg', 'bellafrance.fr', '2025-10-13 08:32:55', 0);

--
-- Déclencheurs `produits`
--
DELIMITER $$
CREATE TRIGGER `after_product_delete` AFTER DELETE ON `produits` FOR EACH ROW BEGIN
    INSERT INTO mouvements_stock (produit_id, type, quantite, prix_total, auteur, commentaire)
    VALUES (OLD.id, 'sortie', OLD.stock, OLD.prix_achat * OLD.stock, OLD.ajoute_par, 'Suppression du produit');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_product_insert` AFTER INSERT ON `produits` FOR EACH ROW BEGIN
    INSERT INTO mouvements_stock (produit_id, type, quantite, prix_total, auteur, commentaire)
    VALUES (NEW.id, 'entrée', NEW.stock, NEW.prix_achat * NEW.stock, NEW.ajoute_par, 'Ajout de produit dans le stock');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_product_update` AFTER UPDATE ON `produits` FOR EACH ROW BEGIN
    DECLARE diff INT;
    SET diff = NEW.stock - OLD.stock;

    IF diff > 0 THEN
        INSERT INTO mouvements_stock (produit_id, type, quantite, prix_total, auteur, commentaire)
        VALUES (NEW.id, 'entrée', diff, NEW.prix_achat * diff, NEW.ajoute_par, 'Augmentation du stock');
    ELSEIF diff < 0 THEN
        INSERT INTO mouvements_stock (produit_id, type, quantite, prix_total, auteur, commentaire)
        VALUES (NEW.id, 'sortie', ABS(diff), NEW.prix_achat * ABS(diff), NEW.ajoute_par, 'Diminution du stock');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_stock_actuel`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_stock_actuel` (
`produit_id` int(11)
,`reference` varchar(50)
,`nom` varchar(255)
,`categorie` varchar(100)
,`prix_achat` decimal(10,2)
,`prix_vente` decimal(10,2)
,`stock_initial` int(11)
,`mouvements` decimal(32,0)
,`stock_total` decimal(33,0)
,`derniere_modification` timestamp
);

-- --------------------------------------------------------

--
-- Structure de la vue `vue_stock_actuel`
--
DROP TABLE IF EXISTS `vue_stock_actuel`;

CREATE ALGORITHM=UNDEFINED DEFINER=`o521868`@`%` SQL SECURITY DEFINER VIEW `vue_stock_actuel`  AS SELECT `p`.`id` AS `produit_id`, `p`.`reference` AS `reference`, `p`.`nom` AS `nom`, `p`.`categorie` AS `categorie`, `p`.`prix_achat` AS `prix_achat`, `p`.`prix_vente` AS `prix_vente`, `p`.`stock` AS `stock_initial`, coalesce(sum((case when (`m`.`type` = 'entrée') then `m`.`quantite` when (`m`.`type` = 'sortie') then -(`m`.`quantite`) else 0 end)),0) AS `mouvements`, (`p`.`stock` + coalesce(sum((case when (`m`.`type` = 'entrée') then `m`.`quantite` when (`m`.`type` = 'sortie') then -(`m`.`quantite`) else 0 end)),0)) AS `stock_total`, max(`m`.`date_mouvement`) AS `derniere_modification` FROM (`produits` `p` left join `mouvements_stock` `m` on((`p`.`id` = `m`.`produit_id`))) GROUP BY `p`.`id`, `p`.`reference`, `p`.`nom`, `p`.`categorie`, `p`.`prix_achat`, `p`.`prix_vente`, `p`.`stock``stock` ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produit_id` (`produit_id`);

--
-- Index pour la table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categorie_id` (`categorie_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `produits`
--
ALTER TABLE `produits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `mouvements_stock`
--
ALTER TABLE `mouvements_stock`
  ADD CONSTRAINT `mouvements_stock_ibfk_1` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`);

--
-- Contraintes pour la table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
