# API MyRocket

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)
![Symfony](https://img.shields.io/badge/Symfony-7.2-green.svg)

Une API REST complète et sécurisée pour la gestion de sites web, de contrats et de maintenance, basée sur Symfony 7.

## 📋 Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Technologies](#technologies)
- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
- [Documentation API](#documentation-api)
- [Tests](#tests)
- [Sécurité](#sécurité)
- [Contribution](#contribution)
- [Licence](#licence)

## ✨ Fonctionnalités

- **Gestion des utilisateurs** : Inscription, authentification, gestion des profils
- **Gestion des sites web** : Création, mise à jour et suppression de sites web
- **Gestion des contrats** : Contrats de création et de maintenance
- **Système de notification** : Envoi d'emails basé sur des événements
- **Sécurité renforcée** : Chiffrement OpenSSL pour les données sensibles
- **Performance optimisée** : Mise en cache pour réduire les requêtes inutiles
- **API RESTful** : Endpoints bien structurés avec JWT pour l'authentification

## 🚀 Technologies

- **[Symfony 7.2](https://symfony.com/)** : Framework PHP moderne et puissant
- **[Doctrine ORM](https://www.doctrine-project.org/)** : ORM pour la gestion de la base de données
- **[Lexik JWT Authentication Bundle](https://github.com/lexik/LexikJWTAuthenticationBundle)** : Authentification JWT sécurisée
- **[Twig](https://twig.symfony.com/)** : Moteur de template pour les emails
- **[Docker](https://www.docker.com/)** : Conteneurisation pour un déploiement simplifié
- **[MySQL](https://www.mysql.com/)** : Système de gestion de base de données

## 🔧 Installation

### Prérequis

- PHP 8.2 ou supérieur
- Composer
- Docker et Docker Compose

### Étapes d'installation

1. Clonez le dépôt :
   ```bash
   git clone https://github.com/votre-utilisateur/api-myrocket.git
   cd api-myrocket
   ```

2. Installez les dépendances :
   ```bash
   composer install
   ```

3. Lancez les conteneurs Docker :
   ```bash
   docker-compose up -d
   ```

4. Configurez les variables d'environnement :
   ```bash
   cp .env .env.local
   # Modifiez .env.local avec vos paramètres
   ```

5. Créez la base de données et exécutez les migrations :
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. Générez les clés JWT :
   ```bash
   php bin/console lexik:jwt:generate-keypair
   ```

## ⚙️ Configuration

### Configuration de la base de données

Par défaut, l'application utilise MySQL. Vous pouvez modifier les paramètres de connexion dans le fichier `.env.local` :

```dotenv
DATABASE_URL="mysql://user:password@127.0.0.1:3306/nom_base_de_donnees?serverVersion=8.0"
```

### Configuration du service d'emails

Configurez les paramètres d'envoi d'emails dans le fichier `.env.local` :

```dotenv
MAILER_DSN=smtp://user:pass@smtp.example.com:port
MAILER_FROM_ADDRESS=no-reply@votredomaine.com
```

### Configuration du chiffrement OpenSSL

Le chiffrement OpenSSL est utilisé pour sécuriser les informations sensibles comme les identifiants d'accès aux serveurs. Configurez la clé de chiffrement dans le fichier `.env.local` :

```dotenv
ENCRYPTION_KEY=votre_clé_de_chiffrement_très_sécurisée
```

## 🔌 Utilisation

### Démarrer le serveur de développement

```bash
symfony server:start
```

### Accéder à l'API

L'API est accessible à l'adresse `http://localhost:8000/api/`.

### Accéder à PHPMyAdmin

PHPMyAdmin est accessible à l'adresse `http://localhost:8080/`.

## 📚 Documentation API

L'API expose les endpoints suivants :

### Authentification

- `POST /api/login_check` : Authentification et génération du token JWT

### Utilisateurs

- `POST /api/user/register` : Inscription d'un nouvel utilisateur
- `GET /api/user/me` : Récupération des informations de l'utilisateur connecté
- `PUT /api/user` : Mise à jour des informations de l'utilisateur
- `DELETE /api/administrateur/user/{uuid}` : Suppression d'un utilisateur (admin uniquement)

### Sites Web

- `GET /api/user/website` : Liste des sites web de l'utilisateur
- `POST /api/administrateur/website` : Création d'un site web (admin uniquement)
- `PUT /api/administrateur/website` : Mise à jour d'un site web (admin uniquement)
- `DELETE /api/administrateur/website/{uuid}` : Suppression d'un site web (admin uniquement)

### Contrats

- `GET /api/user/website/contract/me` : Liste des contrats de l'utilisateur
- `POST /api/administrateur/website-contract` : Création d'un contrat (admin uniquement)
- `DELETE /api/administrateur/website-contract/{uuid}` : Suppression d'un contrat (admin uniquement)

### Contacts

- `POST /api/user/contact` : Création d'un contact
- `GET /api/administrateur/contact` : Liste des contacts (admin uniquement)
- `DELETE /api/administrateur/contact` : Suppression d'un contact (admin uniquement)

## 🛡️ Sécurité

### Authentification JWT

L'API utilise les tokens JWT pour l'authentification. Pour accéder aux endpoints protégés, incluez le token dans l'en-tête de la requête :

```
Authorization: Bearer {token}
```

### Chiffrement OpenSSL

Les informations sensibles comme les mots de passe et les clés SSH sont chiffrées grâce à OpenSSL avant d'être stockées en base de données, garantissant ainsi leur sécurité même en cas de compromission de la base de données.

### Système d'événements

Le système d'événements permet de découpler les actions (comme la création d'un utilisateur) des notifications associées (comme l'envoi d'un email de bienvenue), améliorant ainsi la maintenabilité et la testabilité du code.

### Mise en cache

L'API utilise le système de cache de Symfony pour améliorer les performances et réduire la charge sur la base de données, particulièrement pour les opérations de lecture fréquentes.

## 🔁 Système d'événements

L'API utilise le système d'événements de Symfony pour découpler la logique métier de l'envoi d'emails. Voici les principaux événements :

- `user.registered` : Déclenché lors de l'inscription d'un utilisateur
