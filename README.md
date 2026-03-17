<!-- PROJECT SHIELDS -->

[![Contributors](https://img.shields.io/badge/CONTRIBUTORS-01-blue?style=plastic)](https://github.com/ZouariOmar/AgriGO/graphs/contributors)
[![Forks](https://img.shields.io/badge/FORKS-00-blue?style=plastic)](https://github.com/ZouariOmar/AgriGO/network/members)
[![Stargazers](https://img.shields.io/badge/STARS-01-blue?style=plastic)](https://github.com/ZouariOmar/AgriGO/stargazers)
[![Issues](https://img.shields.io/badge/ISSUES-00-blue?style=plastic)](https://github.com/ZouariOmar/AgriGO/issues)
[![GPL3.0 License](https://img.shields.io/badge/LICENSE-GPL3.0-blue?style=plastic)](LICENSE)

<!-- PROJECT HEADER -->
<div align="center">
  <br />
  <a href="https://github.com/zouari-oss/serinity">
    <img src="https://github.com/zouari-oss/serinity-desktop/raw/main/res/img/logo/serinity-logo-without-bg.png" alt="serinity-web" width="300">
  </a>
  <h6>A desktop & web application dedicated to psychotherapy and personal development</h6>
  <br />
  <br />
</div>

<!-- PROJECT LINKS -->
<p align="center">
  <a href="#overview">Overview</a> •
  <a href="#about-the-project">About the Project</a> •
  <a href="#key-features">Key Features</a> •
  <a href="#how-to-use">How to Use</a> •
  <a href="#download">Download</a> •
  <a href="#emailware">Emailware</a> •
  <a href="#license">License</a> •
  <a href="#contact">Contact</a>
</p>

<!-- PROJECT TAGS -->
<p align="center">
  <img src="https://img.shields.io/badge/python-3670A0?style=for-the-badge&logo=python&logoColor=ffdd54"/>
  <img src="https://img.shields.io/badge/bash_script-%23121011.svg?style=for-the-badge&logo=gnu-bash&logoColor=white"/>
  <img src="https://img.shields.io/badge/Symfony-000000?style=for-the-badge&logo=symfony&logoColor=white"/>
  <img src="https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white"/>
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white"/>
  <img src="https://img.shields.io/badge/css-%23663399.svg?style=for-the-badge&logo=css&logoColor=white"/>
  <img src="https://img.shields.io/badge/Twig-35495E?style=for-the-badge&logo=twig&logoColor=white"/>
  <img src="https://img.shields.io/badge/Doctrine-%23326CE5?style=for-the-badge&logo=doctrine&logoColor=white"/>
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white"/>
  <img src="https://img.shields.io/badge/Cross--Platform-3DDC84?style=for-the-badge&logo=java&logoColor=white"/>
  <img src="https://img.shields.io/badge/Artificial%20Intelligence-000000?style=for-the-badge&logo=openai&logoColor=white"/>
  <img src="https://img.shields.io/badge/Machine%20Learning-102230?style=for-the-badge&logo=scikitlearn&logoColor=F7931E"/>
  <img src="https://img.shields.io/badge/MVC%20Architecture-34495E?style=for-the-badge"/>
  <img src="https://img.shields.io/badge/vercel-%23000000.svg?style=for-the-badge&logo=vercel&logoColor=white"/>
  <img src="https://img.shields.io/badge/Supabase-3ECF8E?style=for-the-badge&logo=supabase&logoColor=white"/>
  <img src="https://img.shields.io/badge/Open%20Source-3DA639?style=for-the-badge&logo=opensourceinitiative&logoColor=white"/>
</p>

<p align="center">
  <a href="doc/" target="_blank">
    <img src="https://www.zippyops.com/userfiles/media/default/web-application-testing.png" alt="serinity-web.gif">
  </a>
</p>

## Overview

Serinity is a **desktop & web application** dedicated to **psychotherapy and personal development**, designed for both **individual users** and **mental health professionals**.
The platform integrates **Artificial Intelligence** to provide personalized emotional analysis, recommendations, and professional therapeutic tools.

## About the Project

- **Theme:** Psychotherapy & Personal Development
- **Platforms:** Desktop & Web
- **Goal:** Improve mental well-being through intelligent tracking, analysis, and guidance
- **Approach:** Modular architecture with AI integration

## Key Features

### User Management

- Authentication & authorization
- Role-based access (Client, Therapist, Admin)
- Secure sessions & audit logs

### Sleep Tracking

- Sleep cycle analysis
- Dream logging & emotional impact

### Mood & Journal

- Daily mood tracking
- Guided emotional questions
- Personal journal with NLP analysis

### Support Network (Forum)

- Community posts & comments
- Secure peer support environment

### Exercises & Resources

- Guided relaxation & meditation exercises
- Multimedia resources (audio, video, text)
- Favorites & progress tracking

### Appointments & Consultations

- Therapist availability management
- Online consultations
- Smart appointment recommendations

### Artificial Intelligence Integration

- Facial recognition for authentication
- NLP-based emotion detection from journals
- AI-assisted self-assessment
- Session summarization & topic extraction
- Intelligent appointment scheduling

## How to Use

### 1. Clone the Repository and Navigate to the Project

```bash
git clone https://github.com/zouari-oss/serinity-web
cd serinity-web/project
```

### 2. Install Dependencies (Composer)

Make sure you have Composer installed, then run:

```bash
composer install
```

### 3. Configure Environment Variables

Update your `.env` file (or `.env.local`) with your database configuration:

```bash
DATABASE_URL="mysql://user:password@127.0.0.1:3306/db_name"
```

### 4. Create Database & Run Migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

> [!IMPORTANT]
> MariaDB must already be installed on your system

### 5. Start the Symfony Server

```bash
symfony server:start
```

Or using PHP:

```bash
php -S 127.0.0.1:8000 -t public
```

### 6. Access the Application

Open your browser and go to:

<http://127.0.0.1:8000>

## Download

You can [download](https://github.com/zouari-oss/serinity-web/releases) the latest installable version of serinity-web for Windows, macOS and Linux.

## Emailware

serinity-web is an emailware. Meaning, if you liked using this app or it has helped you in any way, would like you send as an email at <zouariomar20@gmail.com> or <ghaithbensalah1999@gmail.com> about anything you'd want to say about this software. I'd really appreciate it!

## License

This repository is licensed under the **GPL3.0 License**. You are free to use, modify, and distribute the content. See the [LICENSE](LICENSE) file for details.

## Contact

For questions or suggestions, feel free to reach out the [AUTHORS](AUTHORS)

**Happy Coding!**
