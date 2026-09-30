<?php

namespace App\Support\Faker\Provider\ar_Latn;

use Faker\Provider\Person as BasePerson;

/**
 * Arabic names in Latin script.
 *
 * Faker ships Arabic names in Arabic script (ar_SA, ar_EG, ar_JO), and neither
 * voku/portable-ascii nor ICU's character-level transliteration produces
 * readable Latin forms — Arabic is a consonantal script, so letter-by-letter
 * conversion yields consonant clusters like "sm lHSyn" that no reader would
 * recognise. This provider replaces the Faker Arabic locales with a curated
 * pool of names in Latin-script romanisations familiar from football media,
 * mixing
 * Maghreb and Mashriq anthroponymy so Moroccan, Algerian, Egyptian, Saudi,
 * etc. nationalities all draw plausible names. Pool size is deliberately large
 * enough to keep PlayerGeneratorService's 10-attempt collision retry loop from
 * stalling across many seasons.
 */
class Person extends BasePerson
{
    protected static $firstNameMale = [
        'Achraf', 'Adam', 'Adel', 'Ahmed', 'Aissa', 'Ali', 'Amine', 'Amir',
        'Anis', 'Anouar', 'Ayman', 'Ayoub', 'Aziz', 'Bilal', 'Fares', 'Faouzi',
        'Farouk', 'Fayçal', 'Hakim', 'Hamza', 'Hassan', 'Hicham', 'Hussein',
        'Ibrahim', 'Idriss', 'Ilyes', 'Imad', 'Ismail', 'Issam', 'Jamal',
        'Kamel', 'Karim', 'Khaled', 'Lamine', 'Mahdi', 'Mahmoud', 'Marouane', 'Mehdi',
        'Mohamed', 'Mourad', 'Moustafa', 'Nabil', 'Nacer', 'Nordin', 'Omar',
        'Osama', 'Othmane', 'Rachid', 'Ramy', 'Rayan', 'Riyad', 'Saad',
        'Said', 'Salah', 'Sami', 'Samir', 'Sofiane', 'Soufiane', 'Tarek',
        'Tarik', 'Walid', 'Wassim', 'Yacine', 'Yahia', 'Yassine', 'Younes',
        'Youssef', 'Zakaria', 'Ziyad',
    ];

    protected static $firstNameFemale = [
        'Aicha', 'Amal', 'Amina', 'Amira', 'Asma', 'Asmae', 'Aya', 'Bouchra',
        'Chaima', 'Dalila', 'Doha', 'Dounia', 'Fadwa', 'Farah', 'Fatima',
        'Fatin', 'Ghita', 'Hajar', 'Hanane', 'Hiba', 'Houda', 'Ikram',
        'Ilham', 'Imane', 'Ines', 'Jamila', 'Karima', 'Kawtar', 'Kenza',
        'Khadija', 'Laila', 'Latifa', 'Leila', 'Lina', 'Loubna', 'Maha',
        'Malak', 'Manar', 'Mariam', 'Maryam', 'Meryem', 'Mouna', 'Mounia',
        'Nada', 'Nadia', 'Najwa', 'Nawal', 'Nesrine', 'Nora', 'Nour',
        'Oumaima', 'Oumayma', 'Racha', 'Radhia', 'Randa', 'Rania', 'Rim',
        'Sabrina', 'Safia', 'Sahar', 'Sakina', 'Salma', 'Samia', 'Sana',
        'Sara', 'Selma', 'Siham', 'Sofia', 'Soumia', 'Tania', 'Touria',
        'Wafa', 'Wiam', 'Yasmine', 'Yasmina', 'Zahira', 'Zahra', 'Zeinab',
    ];

    protected static $lastName = [
        'Alaoui', 'Amrani', 'Benali', 'Benjelloun', 'Bennani', 'Berrada', 'Bouazza',
        'Bouchaib', 'Boukhari', 'Bourkia', 'Chaoui', 'Cherkaoui', 'Daoudi', 'El Amrani',
        'El Fassi', 'El Ghazali', 'El Hadri', 'El Idrissi', 'El Kadiri', 'El Khatib',
        'El Mansouri', 'El Ouali', 'Es-Sabbar', 'Fassi', 'Ghali', 'Guerraoui',
        'Hajji', 'Hakimi', 'Hamdaoui', 'Hamdi', 'Hanafi', 'Harrak', 'Hassani',
        'Ibrahimi', 'Idrissi', 'Jabri', 'Jalal', 'Jamal', 'Jebari', 'Kabbaj',
        'Kadiri', 'Karimi', 'Kettani', 'Khalid', 'Khalifa', 'Lahlou', 'Lahrichi',
        'Lamrani', 'Laraki', 'Lazrak', 'Lebbar', 'Mahfoud', 'Majidi', 'Mansouri',
        'Marrakchi', 'Mekouar', 'Mernissi', 'Messari', 'Mouline', 'Moussaoui',
        'Naciri', 'Nejjar', 'Ouazzani', 'Ouchen', 'Oufkir', 'Raji', 'Rami',
        'Rhazi', 'Riad', 'Saadi', 'Sabbagh', 'Sabri', 'Saidi', 'Salaheddine',
        'Salhi', 'Sebti', 'Sekkat', 'Sentissi', 'Skalli', 'Slaoui', 'Sqalli',
        'Tahiri', 'Tangi', 'Tazi', 'Toumi', 'Touzani', 'Yacoubi', 'Yassine',
        'Zaki', 'Zaoui', 'Zeroual', 'Ziani', 'Zineb',
    ];
}
