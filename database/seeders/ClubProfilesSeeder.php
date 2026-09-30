<?php

namespace Database\Seeders;

use App\Models\ClubProfile;
use App\Models\Team;
use App\Support\ClubNames;
use Illuminate\Database\Seeder;

class ClubProfilesSeeder extends Seeder
{
    /**
     * Club profiles with reputation level.
     * Commercial revenue is now calculated algorithmically from stadium_seats × config rate.
     *
     * Names must match the database exactly (seeded from Transfermarkt JSON
     * data). When a club is re-spelled upstream, keep the canonical name here
     * and register the variant in App\Support\ClubNames.
     */
private const CLUB_DATA = [
        // =============================================
        // Argentina - Primera División A (ARG1)
        // =============================================

        // Continental
        'Boca Juniors' => ClubProfile::REPUTATION_CONTINENTAL,
        'CA River Plate' => ClubProfile::REPUTATION_CONTINENTAL,

        // Modest
        'Atlético Lanús' => ClubProfile::REPUTATION_MODEST,
        'CA Huracán' => ClubProfile::REPUTATION_MODEST,
        'CA Independiente' => ClubProfile::REPUTATION_MODEST,
        'CA San Lorenzo de Almagro' => ClubProfile::REPUTATION_MODEST,
        'Gimnasia y Esgrima La Plata' => ClubProfile::REPUTATION_MODEST,
        'San Luis FC' => ClubProfile::REPUTATION_MODEST,
        'Social Atlético Televisión (SAT)' => ClubProfile::REPUTATION_MODEST,
        'Unión de Santa Fe' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Belgrano de Córdoba' => ClubProfile::REPUTATION_LOCAL,
        'CA Banfield' => ClubProfile::REPUTATION_LOCAL,
        'CA Talleres de Córdoba' => ClubProfile::REPUTATION_LOCAL,
        'Ferro Carril Oeste' => ClubProfile::REPUTATION_LOCAL,
        'Newell\'s Old Boys' => ClubProfile::REPUTATION_LOCAL,
        'Racing Club' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // CONCACAF - W Champions Cup (extras) (CCC)
        // =============================================

        // Local
        'Alajuelense' => ClubProfile::REPUTATION_LOCAL,
        'Alianza' => ClubProfile::REPUTATION_LOCAL,
        'Chorrillo' => ClubProfile::REPUTATION_LOCAL,
        'Saprissa' => ClubProfile::REPUTATION_LOCAL,
        'Vancouver Rise' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Spain - Segunda Federación I (E3G1)
        // =============================================

        // Local
        'As Celtas' => ClubProfile::REPUTATION_LOCAL,
        'Bizkerre FT' => ClubProfile::REPUTATION_LOCAL,
        'Burgos CF' => ClubProfile::REPUTATION_LOCAL,
        'CA Osasuna B' => ClubProfile::REPUTATION_LOCAL,
        'CD Arratia' => ClubProfile::REPUTATION_LOCAL,
        'Club de Fútbol Oviedo Moderno Universida' => ClubProfile::REPUTATION_LOCAL,
        'Madrid CFF B' => ClubProfile::REPUTATION_LOCAL,
        'RC Deportivo A Coruña B' => ClubProfile::REPUTATION_LOCAL,
        'Rayo Vallecano' => ClubProfile::REPUTATION_LOCAL,
        'Real Avilés' => ClubProfile::REPUTATION_LOCAL,
        'Real Racing Club de Santander' => ClubProfile::REPUTATION_LOCAL,
        'SD Eibar B' => ClubProfile::REPUTATION_LOCAL,
        'Sporting de Gijón' => ClubProfile::REPUTATION_LOCAL,
        'Victoria CF' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Spain - Segunda Federación III (E3G3)
        // =============================================

        // Local
        'CD Argual' => ClubProfile::REPUTATION_LOCAL,
        'CD Femarguín' => ClubProfile::REPUTATION_LOCAL,
        'CD Getafe Femenino' => ClubProfile::REPUTATION_LOCAL,
        'CD Guiniguada Apolinario' => ClubProfile::REPUTATION_LOCAL,
        'CF Pozuelo' => ClubProfile::REPUTATION_LOCAL,
        'CFF Olympia Las Rozas' => ClubProfile::REPUTATION_LOCAL,
        'Cacereño Femenino Atlético' => ClubProfile::REPUTATION_LOCAL,
        'Club Atlético Málaga' => ClubProfile::REPUTATION_LOCAL,
        'Córdoba CF' => ClubProfile::REPUTATION_LOCAL,
        'EMF Fuensalida' => ClubProfile::REPUTATION_LOCAL,
        'Granada CF B' => ClubProfile::REPUTATION_LOCAL,
        'Real Betis Balompié' => ClubProfile::REPUTATION_LOCAL,
        'Sporting de Huelva' => ClubProfile::REPUTATION_LOCAL,
        'UD Almeria' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Spain - Liga F (ES1)
        // =============================================

        // Elite
        'F.C. Barcelona' => ClubProfile::REPUTATION_ELITE,
        'Real Madrid' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'Club Atlético de Madrid' => ClubProfile::REPUTATION_CONTINENTAL,
        'Real Socieadad' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'CD Tenerife Femenino' => ClubProfile::REPUTATION_ESTABLISHED,
        'Deportivo Alavés Gloriosas' => ClubProfile::REPUTATION_ESTABLISHED,
        'F.C. Sevilla' => ClubProfile::REPUTATION_ESTABLISHED,
        'Logroño United' => ClubProfile::REPUTATION_ESTABLISHED,
        'SD Éibar' => ClubProfile::REPUTATION_ESTABLISHED,

        // Local
        'Athletic Bilbao' => ClubProfile::REPUTATION_LOCAL,
        'Espanyol Barcelona' => ClubProfile::REPUTATION_LOCAL,
        'FC Badalona Women' => ClubProfile::REPUTATION_LOCAL,
        'Granada CF' => ClubProfile::REPUTATION_LOCAL,
        'Madrid CFF' => ClubProfile::REPUTATION_LOCAL,
        'RC Deportivo A Coruña' => ClubProfile::REPUTATION_LOCAL,
        'Valencia Féminas Club de Fútbol' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Spain - Primera Federación (ES2)
        // =============================================

        // Modest
        'CA Osasuna' => ClubProfile::REPUTATION_MODEST,
        'Fundación Albacete' => ClubProfile::REPUTATION_MODEST,
        'Levante UD' => ClubProfile::REPUTATION_MODEST,
        'Real Unión de Tenerife' => ClubProfile::REPUTATION_MODEST,
        'Sport Extremadura' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Alhama CF' => ClubProfile::REPUTATION_LOCAL,
        'Athletic Bilbao II' => ClubProfile::REPUTATION_LOCAL,
        'Atlético Madrid B' => ClubProfile::REPUTATION_LOCAL,
        'CD Tenerife Femenino B' => ClubProfile::REPUTATION_LOCAL,
        'CP Cacereño' => ClubProfile::REPUTATION_LOCAL,
        'FC Barcelona II' => ClubProfile::REPUTATION_LOCAL,
        'Real Madrid B' => ClubProfile::REPUTATION_LOCAL,
        'Real Sociedad San Sebastián B' => ClubProfile::REPUTATION_LOCAL,
        'Villarreal CF' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Spain - Segunda Federación II (ESP3B)
        // =============================================

        // Local
        'AD Villaviciosa de Odón' => ClubProfile::REPUTATION_LOCAL,
        'Atlético Baleares' => ClubProfile::REPUTATION_LOCAL,
        'CD Samper' => ClubProfile::REPUTATION_LOCAL,
        'CE Europa' => ClubProfile::REPUTATION_LOCAL,
        'Elche CF' => ClubProfile::REPUTATION_LOCAL,
        'Espanyol Barcelona B' => ClubProfile::REPUTATION_LOCAL,
        'FC Barcelona C' => ClubProfile::REPUTATION_LOCAL,
        'FC Ona Sant Adria' => ClubProfile::REPUTATION_LOCAL,
        'FC Valencia B' => ClubProfile::REPUTATION_LOCAL,
        'Prainsa' => ClubProfile::REPUTATION_LOCAL,
        'Real Murcia' => ClubProfile::REPUTATION_LOCAL,
        'SD Huesca' => ClubProfile::REPUTATION_LOCAL,
        'SE AEM Lleida' => ClubProfile::REPUTATION_LOCAL,
        'UD Levante B' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // France - Première Ligue (FR1)
        // =============================================

        // Elite
        'OL Lyonnes' => ClubProfile::REPUTATION_ELITE,
        'Paris Saint-Germain' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'Paris FC' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'FC Fleury 91' => ClubProfile::REPUTATION_ESTABLISHED,
        'FC Nantes' => ClubProfile::REPUTATION_ESTABLISHED,
        'Le Havre AC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Montpellier FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Olympique de Marseille' => ClubProfile::REPUTATION_ESTABLISHED,
        'RC Lens' => ClubProfile::REPUTATION_ESTABLISHED,
        'RC Strasbourg' => ClubProfile::REPUTATION_ESTABLISHED,
        'Toulouse FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'US Saint-Malo' => ClubProfile::REPUTATION_ESTABLISHED,

        // =============================================
        // England - Women's Super League (GB1)
        // =============================================

        // Elite
        'Arsenal FC' => ClubProfile::REPUTATION_ELITE,
        'Chelsea LFC' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'Manchester City LFC' => ClubProfile::REPUTATION_CONTINENTAL,
        'Manchester United' => ClubProfile::REPUTATION_CONTINENTAL,
        'Tottenham Hotspur LFC' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'Aston Villa LFC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Brighton & Hove Albion WFC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Charlton Athletic' => ClubProfile::REPUTATION_ESTABLISHED,
        'Crystal Palace LFC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Everton LFC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Liverpool LFC' => ClubProfile::REPUTATION_ESTABLISHED,
        'West Ham United LFC' => ClubProfile::REPUTATION_ESTABLISHED,

        // Local
        'Birmingham City LFC' => ClubProfile::REPUTATION_LOCAL,
        'London City Lionesses' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Italy - Serie A Women (IT1)
        // =============================================

        // Continental
        'ACF Mailand' => ClubProfile::REPUTATION_CONTINENTAL,
        'AS Rom' => ClubProfile::REPUTATION_CONTINENTAL,
        'Inter Mailand' => ClubProfile::REPUTATION_CONTINENTAL,
        'Juventus Football Club' => ClubProfile::REPUTATION_CONTINENTAL,
        'SS Lazio' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'ACF Florenz' => ClubProfile::REPUTATION_ESTABLISHED,
        'ASD Napoli Femminile' => ClubProfile::REPUTATION_ESTABLISHED,
        'Como 1907' => ClubProfile::REPUTATION_ESTABLISHED,

        // Local
        'Parma Calcio 1913' => ClubProfile::REPUTATION_LOCAL,
        'SSD Riozzese Como' => ClubProfile::REPUTATION_LOCAL,
        'Ternana Calcio Femminile' => ClubProfile::REPUTATION_LOCAL,
        'US Sassuolo Calcio' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Germany - Frauen-Bundesliga (L1)
        // =============================================

        // Elite
        'Bayern München' => ClubProfile::REPUTATION_ELITE,
        'VfL Wolfsburg' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'Bayer Leverkusen' => ClubProfile::REPUTATION_CONTINENTAL,
        'Eintracht Frankfurt' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        '1. FC Köln' => ClubProfile::REPUTATION_ESTABLISHED,
        '1. FC Nuremberg' => ClubProfile::REPUTATION_ESTABLISHED,
        '1. FSV Mainz 05' => ClubProfile::REPUTATION_ESTABLISHED,
        '1899 Hoffenheim' => ClubProfile::REPUTATION_ESTABLISHED,
        'Hamburger SV' => ClubProfile::REPUTATION_ESTABLISHED,
        'Rasenballsport Leipzig' => ClubProfile::REPUTATION_ESTABLISHED,
        'SC Freiburg' => ClubProfile::REPUTATION_ESTABLISHED,
        'VfB Stuttgart 1893' => ClubProfile::REPUTATION_ESTABLISHED,

        // Local
        '1. FC Union Berlin' => ClubProfile::REPUTATION_LOCAL,
        'Werder Bremen' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Mexico - Liga MX Femenil (MEX1)
        // =============================================

        // Continental
        'Club América' => ClubProfile::REPUTATION_CONTINENTAL,
        'Rayados de Monterrey' => ClubProfile::REPUTATION_CONTINENTAL,
        'Tigres de la UANL' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'Atlante FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Atlas Guadalajara' => ClubProfile::REPUTATION_ESTABLISHED,
        'CF Pachuca' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club Atlético de San Luis' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club Deportivo Guadalajara' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club Deportivo Toluca' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club León' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club Necaxa' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club Puebla' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club Santos Laguna' => ClubProfile::REPUTATION_ESTABLISHED,
        'Cruz Azul' => ClubProfile::REPUTATION_ESTABLISHED,
        'FC Juárez' => ClubProfile::REPUTATION_ESTABLISHED,
        'Pumas UNAM' => ClubProfile::REPUTATION_ESTABLISHED,
        'Xolos Tijuana' => ClubProfile::REPUTATION_ESTABLISHED,

        // Local
        'Gallos Blancos de Querétaro' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Netherlands - Eredivisie Vrouwen (NL1)
        // =============================================

        // Continental
        'Ajax Amsterdam' => ClubProfile::REPUTATION_CONTINENTAL,
        'FC Twente' => ClubProfile::REPUTATION_CONTINENTAL,
        'FCE/PSV' => ClubProfile::REPUTATION_CONTINENTAL,
        'Feyenoord Rotterdam' => ClubProfile::REPUTATION_CONTINENTAL,

        // Modest
        'ADO Den Haag' => ClubProfile::REPUTATION_MODEST,
        'AZ Alkmaar' => ClubProfile::REPUTATION_MODEST,
        'De Graafschap' => ClubProfile::REPUTATION_MODEST,
        'FC Utrecht' => ClubProfile::REPUTATION_MODEST,
        'FC Zwolle' => ClubProfile::REPUTATION_MODEST,
        'SC Heerenveen' => ClubProfile::REPUTATION_MODEST,

        // =============================================
        // Portugal - Liga BPI (PO1)
        // =============================================

        // Continental
        'FC Porto' => ClubProfile::REPUTATION_CONTINENTAL,
        'SL Benfica' => ClubProfile::REPUTATION_CONTINENTAL,
        'Sporting Clube de Portugal' => ClubProfile::REPUTATION_CONTINENTAL,

        // Modest
        'C.S. Marítimo' => ClubProfile::REPUTATION_MODEST,
        'Rio Ave FC' => ClubProfile::REPUTATION_MODEST,
        'SC Uniao Torreense' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Racing Power Football Club' => ClubProfile::REPUTATION_LOCAL,
        'Sporting Clube de Braga Feminino' => ClubProfile::REPUTATION_LOCAL,
        'Valadares Gaia Futebol Clube' => ClubProfile::REPUTATION_LOCAL,
        'Vitória Sport Clube Guimarães' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Switzerland - AXA Women's Super League (SUI1)
        // =============================================

        // Continental
        'FC Zürich' => ClubProfile::REPUTATION_CONTINENTAL,
        'Servette FC Chênois Féminin' => ClubProfile::REPUTATION_CONTINENTAL,

        // Modest
        'BSC YB Frauen' => ClubProfile::REPUTATION_MODEST,
        'FC Aarau Frauen' => ClubProfile::REPUTATION_MODEST,
        'FC Luzern' => ClubProfile::REPUTATION_MODEST,
        'FC Rapperswil-Jona' => ClubProfile::REPUTATION_MODEST,
        'FC St. Gallen 1879' => ClubProfile::REPUTATION_MODEST,
        'Yverdon Sport FC' => ClubProfile::REPUTATION_MODEST,

        // Local
        'FC Basel 1893' => ClubProfile::REPUTATION_LOCAL,
        'Grasshopper Club Zürich' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // France - Seconde Ligue (FRA2)
        // =============================================

        // Established
        'AS Saint-Étienne' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'AJ Auxerre' => ClubProfile::REPUTATION_MODEST,
        'AS Cannes' => ClubProfile::REPUTATION_MODEST,
        'FC Metz' => ClubProfile::REPUTATION_MODEST,
        'Grenoble Foot 38' => ClubProfile::REPUTATION_MODEST,
        'Le Mans FC' => ClubProfile::REPUTATION_MODEST,
        'LOSC Lille' => ClubProfile::REPUTATION_MODEST,
        'OGC Nice' => ClubProfile::REPUTATION_MODEST,
        'Thonon Évian Grand Genève FC' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Bourges FC' => ClubProfile::REPUTATION_LOCAL,
        'Racing Club Roubaix Wervicq' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Italy - Serie B Femminile (ITA2)
        // =============================================

        // Established
        'Genoa CFC' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'ACF Arezzo' => ClubProfile::REPUTATION_MODEST,
        'ACF Brescia' => ClubProfile::REPUTATION_MODEST,
        'Bologna FC 1909' => ClubProfile::REPUTATION_MODEST,
        'Cesena FC' => ClubProfile::REPUTATION_MODEST,
        'FC Lumezzane Women' => ClubProfile::REPUTATION_MODEST,
        'Frosinone Calcio' => ClubProfile::REPUTATION_MODEST,
        'Hellas Verona' => ClubProfile::REPUTATION_MODEST,
        'Venezia FC' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Catania FC' => ClubProfile::REPUTATION_LOCAL,
        'Donna Roma FC' => ClubProfile::REPUTATION_LOCAL,
        'Moncalieri Women' => ClubProfile::REPUTATION_LOCAL,
        'San Marino Academy' => ClubProfile::REPUTATION_LOCAL,
        'Vicenza WFC' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Germany - 2. Frauen-Bundesliga (DEU2)
        // =============================================

        // Established
        '1. FFC Turbine Potsdam' => ClubProfile::REPUTATION_ESTABLISHED,
        'FC Carl Zeiss Jena' => ClubProfile::REPUTATION_ESTABLISHED,
        'SGS Essen' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        '1. FC Köln II' => ClubProfile::REPUTATION_MODEST,
        'Borussia Mönchengladbach' => ClubProfile::REPUTATION_MODEST,
        'Eintracht Frankfurt II' => ClubProfile::REPUTATION_MODEST,
        'FC Ingolstadt 04' => ClubProfile::REPUTATION_MODEST,
        'SC Sand' => ClubProfile::REPUTATION_MODEST,
        'SG 99 Andernach' => ClubProfile::REPUTATION_MODEST,
        'SV Meppen' => ClubProfile::REPUTATION_MODEST,
        'TSG 1899 Hoffenheim II' => ClubProfile::REPUTATION_MODEST,
        'VfL Bochum' => ClubProfile::REPUTATION_MODEST,

        // Local
        'FC Viktoria 1889 Berlin' => ClubProfile::REPUTATION_LOCAL,
        'Hertha BSC' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // England - WSL2 (ENG2)
        // =============================================

        // Established
        'Leicester City' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Bristol City' => ClubProfile::REPUTATION_MODEST,
        'Durham' => ClubProfile::REPUTATION_MODEST,
        'Ipswich Town' => ClubProfile::REPUTATION_MODEST,
        'Newcastle United' => ClubProfile::REPUTATION_MODEST,
        'Nottingham Forest' => ClubProfile::REPUTATION_MODEST,
        'Sheffield United' => ClubProfile::REPUTATION_MODEST,
        'Southampton' => ClubProfile::REPUTATION_MODEST,
        'Sunderland' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Burnley' => ClubProfile::REPUTATION_LOCAL,
        'Watford' => ClubProfile::REPUTATION_LOCAL,
        'Wolverhampton Wanderers' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // USA - NWSL (USA1)
        // =============================================

        // Continental
        'Orlando Pride' => ClubProfile::REPUTATION_CONTINENTAL,
        'Portland Thorns FC' => ClubProfile::REPUTATION_CONTINENTAL,
        'Washington Spirit' => ClubProfile::REPUTATION_CONTINENTAL,
        'Kansas City Current' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'Bay FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Boston Legacy FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Chicago Stars FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Denver Summit FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Gotham FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Houston Dash' => ClubProfile::REPUTATION_ESTABLISHED,
        'Racing Louisville FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'San Diego Wave FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Seattle Reign FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Utah Royals FC' => ClubProfile::REPUTATION_ESTABLISHED,

        // Local
        'Angel City FC' => ClubProfile::REPUTATION_LOCAL,
        'North Carolina Courage' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Argentina - Primera División A (ARG1)
        // =============================================

        // Continental
        'Boca Juniors' => ClubProfile::REPUTATION_CONTINENTAL,
        'River Plate' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'San Lorenzo' => ClubProfile::REPUTATION_ESTABLISHED,
        'Racing Club' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Belgrano' => ClubProfile::REPUTATION_MODEST,
        'Gimnasia y Esgrima' => ClubProfile::REPUTATION_MODEST,
        'Banfield' => ClubProfile::REPUTATION_MODEST,
        "Newell's" => ClubProfile::REPUTATION_MODEST,
        'Talleres' => ClubProfile::REPUTATION_MODEST,
        'Huracán' => ClubProfile::REPUTATION_MODEST,
        'Independiente' => ClubProfile::REPUTATION_MODEST,
        'Lanús' => ClubProfile::REPUTATION_MODEST,

        // Local
        'San Luis FC' => ClubProfile::REPUTATION_LOCAL,
        'Ferro Carril Oeste' => ClubProfile::REPUTATION_LOCAL,
        'Social Atlético Televisión' => ClubProfile::REPUTATION_LOCAL,
        'Unión de Santa Fe' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Brasil - Brasileirão Feminino Série A1 (BRA1)
        // =============================================

        // Continental
        'Corinthians' => ClubProfile::REPUTATION_CONTINENTAL,
        'Palmeiras' => ClubProfile::REPUTATION_CONTINENTAL,
        'São Paulo' => ClubProfile::REPUTATION_CONTINENTAL,
        'Flamengo' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'Internacional' => ClubProfile::REPUTATION_ESTABLISHED,
        'Grêmio' => ClubProfile::REPUTATION_ESTABLISHED,
        'Santos' => ClubProfile::REPUTATION_ESTABLISHED,
        'Ferroviária' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Cruzeiro' => ClubProfile::REPUTATION_MODEST,
        'Atlético Mineiro' => ClubProfile::REPUTATION_MODEST,
        'Bahia' => ClubProfile::REPUTATION_MODEST,
        'Botafogo' => ClubProfile::REPUTATION_MODEST,
        'Fluminense' => ClubProfile::REPUTATION_MODEST,
        'Red Bull Bragantino' => ClubProfile::REPUTATION_MODEST,

        // Local
        'América Mineiro' => ClubProfile::REPUTATION_LOCAL,
        'Juventude' => ClubProfile::REPUTATION_LOCAL,
        'Mixto' => ClubProfile::REPUTATION_LOCAL,
        'Vitória' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // México - Liga MX Femenil (MEX1)
        // =============================================

        // Continental
        'Tigres de la UANL' => ClubProfile::REPUTATION_CONTINENTAL,
        'Club América' => ClubProfile::REPUTATION_CONTINENTAL,
        'Rayados de Monterrey' => ClubProfile::REPUTATION_CONTINENTAL,
        'Club Deportivo Guadalajara' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'CF Pachuca' => ClubProfile::REPUTATION_ESTABLISHED,
        'Pumas UNAM' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Cruz Azul' => ClubProfile::REPUTATION_MODEST,
        'Atlas Guadalajara' => ClubProfile::REPUTATION_MODEST,
        'Club Deportivo Toluca' => ClubProfile::REPUTATION_MODEST,
        'Club León' => ClubProfile::REPUTATION_MODEST,
        'Xolos Tijuana' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Club Santos Laguna' => ClubProfile::REPUTATION_LOCAL,
        'Club Necaxa' => ClubProfile::REPUTATION_LOCAL,
        'Club Atlético de San Luis' => ClubProfile::REPUTATION_LOCAL,
        'Club Puebla' => ClubProfile::REPUTATION_LOCAL,
        'FC Juárez' => ClubProfile::REPUTATION_LOCAL,
        'Gallos Blancos de Querétaro' => ClubProfile::REPUTATION_LOCAL,
        'Atlante FC' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // CONCACAF W Champions Cup extras (CONCACHAMPIONS)
        // =============================================

        // Established
        'Alajuelense' => ClubProfile::REPUTATION_ESTABLISHED,
        'Saprissa' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Vancouver Rise' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Alianza' => ClubProfile::REPUTATION_LOCAL,
        'Chorrillo' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // Copa Libertadores Femenina extras (LIBERTADORES)
        // =============================================

        // Continental
        'Colo-Colo' => ClubProfile::REPUTATION_CONTINENTAL,
        'Universidad de Chile' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'Olimpia' => ClubProfile::REPUTATION_ESTABLISHED,
        'Libertad' => ClubProfile::REPUTATION_ESTABLISHED,
        'Independiente Santa Fe' => ClubProfile::REPUTATION_ESTABLISHED,
        'Deportivo Cali' => ClubProfile::REPUTATION_ESTABLISHED,
        'Independiente del Valle' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Nacional' => ClubProfile::REPUTATION_MODEST,
        'LDU Quito' => ClubProfile::REPUTATION_MODEST,
        'Universitario de Deportes' => ClubProfile::REPUTATION_MODEST,

        // Local
        'Caracas FC' => ClubProfile::REPUTATION_LOCAL,
        'Club Bolívar' => ClubProfile::REPUTATION_LOCAL,

    ];

    /**
     * Curated reputation tier for national teams keyed by FIFA code.
     * Reputation drives AI mentality, instructions, and the formation-bias
     * fallback pool for any national-team match (currently World Cup
     * tournament mode). Without this, every national team would default to
     * REPUTATION_LOCAL and behave like a small-club squad regardless of
     * stature.
     *
     * Calibrated against current strength (FIFA ranking + recent tournament
     * performance) rather than historic prestige alone — Hungary is not on
     * this list because they aren't at WC2026; Morocco sits at CONTINENTAL
     * after their 2022 semifinal run, etc.
     */
    private const NATIONAL_TEAM_REPUTATION = [
        // Elite — title contenders
        'ARG' => ClubProfile::REPUTATION_ELITE,        // World champions
        'BRA' => ClubProfile::REPUTATION_ELITE,
        'FRA' => ClubProfile::REPUTATION_ELITE,
        'ESP' => ClubProfile::REPUTATION_ELITE,        // Euro 2024 champions
        'ENG' => ClubProfile::REPUTATION_ELITE,
        'GER' => ClubProfile::REPUTATION_ELITE,
        'POR' => ClubProfile::REPUTATION_ELITE,
        'NED' => ClubProfile::REPUTATION_ELITE,

        // Continental — strong regular contenders
        'BEL' => ClubProfile::REPUTATION_CONTINENTAL,
        'CRO' => ClubProfile::REPUTATION_CONTINENTAL, // 2022 semifinalists
        'URU' => ClubProfile::REPUTATION_CONTINENTAL,
        'COL' => ClubProfile::REPUTATION_CONTINENTAL, // Copa America 2024 finalist
        'MAR' => ClubProfile::REPUTATION_CONTINENTAL, // 2022 semifinalists
        'SEN' => ClubProfile::REPUTATION_CONTINENTAL,
        'SUI' => ClubProfile::REPUTATION_CONTINENTAL,
        'MEX' => ClubProfile::REPUTATION_CONTINENTAL,
        'USA' => ClubProfile::REPUTATION_CONTINENTAL,
        'JPN' => ClubProfile::REPUTATION_CONTINENTAL,
        'TUR' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established — qualified regularly, mid-tier
        'SWE' => ClubProfile::REPUTATION_ESTABLISHED,
        'NOR' => ClubProfile::REPUTATION_ESTABLISHED, // Haaland-led generation
        'AUT' => ClubProfile::REPUTATION_ESTABLISHED,
        'EGY' => ClubProfile::REPUTATION_ESTABLISHED,
        'CIV' => ClubProfile::REPUTATION_ESTABLISHED, // AFCON 2023 winners
        'IRN' => ClubProfile::REPUTATION_ESTABLISHED,
        'KOR' => ClubProfile::REPUTATION_ESTABLISHED,
        'AUS' => ClubProfile::REPUTATION_ESTABLISHED,
        'CZE' => ClubProfile::REPUTATION_ESTABLISHED,
        'SCO' => ClubProfile::REPUTATION_ESTABLISHED,
        'ECU' => ClubProfile::REPUTATION_ESTABLISHED,
        'PAR' => ClubProfile::REPUTATION_ESTABLISHED,
        'ALG' => ClubProfile::REPUTATION_ESTABLISHED,
        'TUN' => ClubProfile::REPUTATION_ESTABLISHED,
        'GHA' => ClubProfile::REPUTATION_ESTABLISHED,
        'KSA' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest — first-time or sporadic qualifiers
        'QAT' => ClubProfile::REPUTATION_MODEST,
        'BIH' => ClubProfile::REPUTATION_MODEST,
        'CAN' => ClubProfile::REPUTATION_MODEST,
        'NZL' => ClubProfile::REPUTATION_MODEST,
        'CPV' => ClubProfile::REPUTATION_MODEST,
        'COD' => ClubProfile::REPUTATION_MODEST,
        'IRQ' => ClubProfile::REPUTATION_MODEST,
        'JOR' => ClubProfile::REPUTATION_MODEST,
        'UZB' => ClubProfile::REPUTATION_MODEST,
        'PAN' => ClubProfile::REPUTATION_MODEST,
        'CUR' => ClubProfile::REPUTATION_MODEST,

        // Local — outsiders / debutants
        'RSA' => ClubProfile::REPUTATION_LOCAL,
        'HAI' => ClubProfile::REPUTATION_LOCAL,
    ];

    /**
     * Curated preferred formation for national teams keyed by FIFA code.
     * Reflects current managerial identity. Unlisted national teams fall
     * back to the reputation-tier formation pool in FormationBiasResolver.
     */
    private const NATIONAL_TEAM_PREFERRED_FORMATION = [
        'ARG' => '4-1-2-3',
        'BRA' => '4-2-1-3',
        'FRA' => '4-1-2-3',
        'ESP' => '4-2-1-3',
        'ENG' => '3-4-3',
        'GER' => '4-2-3-1',
        'POR' => '4-3-3',
        'NED' => '4-3-3',
        'BEL' => '3-4-3',
        'CRO' => '4-3-3',
        'URU' => '4-4-2',
        'COL' => '4-2-3-1',
        'MAR' => '4-3-3',
        'SEN' => '4-3-3',
        'SUI' => '4-2-3-1',
        'MEX' => '4-3-3',
        'USA' => '4-3-3',
        'JPN' => '4-2-3-1',
        'TUR' => '4-2-3-1',
        'NOR' => '4-3-3',
        'AUT' => '4-3-3',
        'KOR' => '4-2-3-1',
        'AUS' => '4-2-3-1',
        'IRN' => '5-4-1',
        'KSA' => '4-2-3-1',
        'QAT' => '5-3-2',
        'PAR' => '4-4-2',
    ];

    /**
     * Curated tactical aggression (-2..+2) for national teams keyed by FIFA
     * code. International football skews more conservative than club football
     * (cup format, less drilled-in pressing), so most teams sit at 0; only
     * sides with a pronounced identity shift one or two notches.
     */
    private const NATIONAL_TEAM_TACTICAL_AGGRESSION = [
        'ESP' => 1,
        'BRA' => 1,
        'POR' => 1,
        'NED' => 1,
        'AUT' => 1,
        'JPN' => 1,
        'SEN' => 1,
        'MAR' => -1,
        'URU' => -1,
        'SUI' => -1,
        'IRN' => -2,
        'KSA' => -1,
        'QAT' => -1,
        'NZL' => -1,
        'JOR' => -1,
        'PAN' => -1,
        'HAI' => -1,
        'RSA' => -1,
    ];

    /**
     * Curated per-club fan_loyalty on a 0-10 editorial scale. Anchor for
     * TeamReputation.base_loyalty at game start. Only clubs whose loyalty
     * differs from the neutral midpoint need an entry; everyone else
     * defaults to ClubProfile::FAN_LOYALTY_DEFAULT (5).
     *
     * With the DemandCurveService formula (0.50 + loyalty/100 × 0.45),
     * each loyalty point shifts the base fill rate by ~4.5 percentage
     * points. Calibrated against real La Liga / La Liga 2 occupancy:
     *
     *   10 → ~95%  iconic / cult (Racing 93.7%)
     *    9 → ~90%  huge passionate (Athletic 89.8%, Valencia 89.9%)
     *    8 → ~86%  strong (Real Madrid 87.5%, Osasuna 87.0%)
     *    7 → ~82%  good (Rayo 81.3%, Sevilla 79.4%)
     *    6 → ~77%  above avg (Villarreal 77.1%, Espanyol 75.7%)
     *    5 → ~73%  avg (default) (Deportivo 71.0%, Sporting 70.8%)
     *    4 → ~68%  below avg (Barcelona 67.7%, Granada 67.8%)
     *    3 → ~64%  small (Cádiz 64.1%, Huesca 63.4%)
     *    2 → ~59%  low (Valladolid 59.0%, Eibar 57.5%)
     *    1 → ~55%  very low (Mirandés 54.2%)
     *    0 → ~50%  minimal (Getafe 48.7%, Andorra 45.2%)
     */
    private const FAN_LOYALTY_OVERRIDES = [
        // ── Spain — La Liga ──────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data.
        'Real Madrid' => 8,              // 87.5%
        'F.C. Barcelona' => 6,             // 67.7%
        'Club Atlético de Madrid' => 8,       // 87.2%
        'Athletic Bilbao' => 9,            // 89.8%
        'Real Betis Balompié' => 7,      // 84.1%
        'Villarreal CF' => 5,            // 77.1%
        'F.C. Sevilla' => 7,              // 79.4%
        'Real Socieadad' => 7,            // 78.7%
        'Valencia Féminas Club de Fútbol' => 9,              // 89.9%
        'Espanyol Barcelona' => 7,   // 75.7%
        'RC Celta' => 9,                 // 89.0%
        'RCD Mallorca' => 5,             // 66.8%
        'CA Osasuna' => 8,              // 87.0%
        'Getafe CF' => 3,               // 48.7%
        'Rayo Vallecano' => 7,           // 81.3%
        'Girona FC' => 4,               // 79.5%
        'Deportivo Alavés Gloriosas' => 7,         // 83.2%
        'Elche CF' => 6,                // 84.3%
        'Levante UD' => 4,              // 76.1%
        'Real Oviedo' => 7,             // 83.0%

        // ── Spain — La Liga 2 ────────────────────────────────────────
        'Racing Santander' => 7,        // 93.7%
        'Málaga CF' => 7,               // 82.6%
        'Deportivo A Coruña' => 6,   // 71.0%
        'Sporting Gijón' => 5,          // 70.8%
        'Real Zaragoza' => 5,            // 74.1%
        'Córdoba CF' => 5,              // 72.2%
        'CD Castellón' => 6,             // 75.3%
        'Burgos CF' => 6,               // 74.9%
        'Cultural Leonesa' => 4,         // 74.4%
        'AD Ceuta FC' => 3,             // 72.8%
        'UD Almería' => 4,              // 69.5%
        'Granada CF' => 4,              // 67.8%
        'CD Leganés' => 4,              // 69.1%
        'Albacete Balompié' => 3,        // 64.6%
        'Cádiz CF' => 5,                // 64.1%
        'SD Huesca' => 3,               // 63.4%
        'Real Valladolid CF' => 3,       // 59.0%
        'UD Las Palmas' => 4,            // 57.4%
        'SD Eibar' => 4,                // 57.5%
        'CD Mirandés' => 4,             // 54.2%
        'Real Sociedad B' => 2,          // 65.4%
        'FC Andorra' => 3,              // 45.2%

        // ── England ──────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data. English football
        // runs near-capacity across the board — every club in the data
        // set exceeds 91%, so loyalty 10 for all.
        'Nottingham Forest' => 8,       // 100.1%
        'West Ham United LFC' => 8,         // 99.9%
        'Newcastle United' => 9,        // 99.7%
        'Brentford FC' => 7,           // 99.3%
        'Arsenal FC' => 8,             // 99.2%
        'Manchester United' => 8,       // 98.8%
        'AFC Bournemouth' => 7,         // 98.8%
        'Everton LFC' => 8,             // 98.7%
        'Liverpool LFC' => 8,           // 98.6%
        'Brighton & Hove Albion WFC' => 6,  // 98.4%
        'Crystal Palace LFC' => 7,          // 97.7%
        'Aston Villa LFC' => 8,            // 97.5%
        'Tottenham Hotspur LFC' => 7,       // 97.0%
        'Leeds United' => 6,           // 96.9%
        'Burnley FC' => 6,             // 95.4%
        'Chelsea LFC' => 7,             // 95.3%
        'Sunderland AFC' => 7,         // 95.2%
        'Manchester City LFC' => 6,         // 94.8%
        'Wolverhampton Wanderers' => 6, // 94.0%
        'Fulham FC' => 6,               // 91.8%

        // ── Germany ──────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data. The Bundesliga's
        // 50+1 rule, standing sections, and cheap tickets produce near-
        // universal sellouts — almost every club sits at loyalty 10.
        'Bayern München' => 10,           // 100.0%
        'Borussia Dortmund' => 10,       // 100.0%
        'Hamburger SV' => 9,           // 99.9%
        '1. FC Union Berlin' => 9,       // 99.9%
        'FC St. Pauli' => 9,           // 99.8%
        '1. FC Köln' => 9,              // 99.8%
        'Bayer Leverkusen' => 8,     // 99.4%
        'Eintracht Frankfurt' => 8,     // 99.3%
        'Werder Bremen' => 8,        // 98.8%
        'SC Freiburg' => 6,            // 98.8%
        '1.FC Heidenheim 1846' => 7,    // 98.7%
        'VfB Stuttgart 1893' => 5,          // 97.9%
        'FC Augsburg' => 5,            // 96.8%
        '1. FSV Mainz 05' => 6,         // 95.0%
        'Borussia Mönchengladbach' => 7, // 94.0%
        'Rasenballsport Leipzig' => 5,              // 92.8%
        '1899 Hoffenheim' => 4,      // 86.6%
        'VfL Wolfsburg' => 4,            // 83.8%

        // ── France ───────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data.
        'RC Strasbourg' => 7,    // 104.7% (standing overfill)
        'RC Lens' => 7,                // 98.2%
        'Paris Saint-Germain' => 8,     // 97.8%
        'Stade Brestois 29' => 8,       // 95.0%
        'Olympique de Marseille' => 9,     // 93.2%
        'Stade Rennais FC' => 7,        // 93.2%
        'FC Lorient' => 8,              // 90.6%
        'AJ Auxerre' => 7,             // 88.3%
        'LOSC Lille' => 7,              // 85.5%
        'Paris FC' => 6,                // 84.1%
        'OL Lyonnes' => 6,          // 82.8%
        'FC Metz' => 7,                 // 78.5%
        'FC Nantes' => 6,               // 78.2%
        'Le Havre AC' => 6,             // 75.7%
        'Toulouse FC' => 5,             // 73.8%
        'Angers SCO' => 3,              // 64.4%
        'OGC Nice' => 2,                // 60.1%
        'AS Monaco' => 1,               // 43.8%

        // ── Italy ────────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data.
        'Cagliari Calcio' => 8,         // 98.0%
        'Juventus Football Club' => 9,             // 96.9%
        'ACF Mailand' => 9,               // 94.2%
        'ASD Napoli Femminile' => 9,             // 93.3%
        'Inter Mailand' => 8,              // 92.4%
        'Atalanta BC' => 7,             // 90.9%
        'AS Rom' => 8,                 // 88.4%
        'Genoa CFC' => 7,              // 88.8%
        'Como 1907' => 7,               // 87.4%
        'Udinese Calcio' => 8,          // 86.8%
        'Venezia FC' => 6,              // 86.2%
        'Parma Calcio 1913' => 6,        // 85.6%
        'Torino FC' => 6,               // 82.8%
        'US Lecce' => 6,                // 82.4%
        'Bologna FC 1909' => 5,          // 76.7%
        'AC Monza' => 3,                // 64.9%
        'Hellas Verona' => 3,            // 63.5%
        'SS Lazio' => 3,                // 62.4%
        'FC Empoli' => 2,               // 54.3%
        'ACF Florenz' => 3,           // 47.2%

        // ── Portugal ─────────────────────────────────────────────────
        // Portuguese grounds run well below the top-five average: the big
        // three fill large stadiums, but most of the division plays in
        // half-empty municipal grounds built for Euro 2004.
        'SL Benfica' => 7,              // ~82%, 64k Luz
        'FC Porto' => 7,                // ~82%
        'Sporting Clube de Portugal' => 8,             // ~86%
        'Sporting Clube de Braga Feminino' => 4,
        'Vitória Sport Clube Guimarães' => 6,
        'Boavista FC' => 4,
        'CD Nacional' => 3,
        'Rio Ave FC' => 3,
        'Moreirense FC' => 2,
        'FC Famalicão' => 2,
        'Gil Vicente FC' => 2,
        'GD Estoril Praia' => 2,
        'Casa Pia AC' => 1,
        'FC Arouca' => 2,
        'CD Santa Clara' => 3,
        'CF Estrela Amadora' => 2,
        'AVS Futebol SAD' => 1,
        'FC Alverca' => 1,
        'CD Tondela' => 2,
        'Académico Viseu FC' => 2,

        // ── Netherlands ──────────────────────────────────────────────
        // The opposite profile: small grounds, near-permanent sell-outs
        // and season-ticket waiting lists throughout the division.
        'Ajax Amsterdam' => 9,
        'FCE/PSV' => 9,
        'Feyenoord Rotterdam' => 9,
        'FC Groningen' => 8,
        'FC Utrecht' => 8,
        'FC Twente' => 8,
        'AZ Alkmaar' => 7,
        'SC Heerenveen' => 7,
        'NEC Nijmegen' => 8,
        'Go Ahead Eagles' => 8,
        'NAC Breda' => 8,
        'Sparta Rotterdam' => 7,
        'FC Zwolle' => 7,
        'FC Volendam' => 7,
        'Willem II Tilburg' => 7,
        'Heracles Almelo' => 6,
        'Fortuna Sittard' => 5,
        'Excelsior Rotterdam' => 5,
        'SC Telstar' => 5,
        'ADO Den Haag' => 8,            // fervent Haagse support
        'SC Cambuur Leeuwarden' => 9,   // routinely sells out the Kooi

        // ── Switzerland ──────────────────────────────────────────────
        'Servette FC Chênois Féminin' => 7,
        'FC Zürich' => 7,
        'Grasshopper Club Zürich' => 6,
        'BSC YB Frauen' => 6,
        'FC Basel 1893' => 6,
        'FC Luzern' => 5,
        'FC St. Gallen 1879' => 5,
        'Yverdon Sport FC' => 5,

        // ── France (Seconde Ligue) ───────────────────────────────────
        'AS Saint-Étienne' => 7,

        // ── Italy (Serie B Femminile) ────────────────────────────────
        'Genoa CFC' => 6,

        // ── Germany (2. Frauen-Bundesliga) ───────────────────────────
        '1. FFC Turbine Potsdam' => 7,
        'SGS Essen' => 6,
        'FC Carl Zeiss Jena' => 6,

        // ── England (WSL2) ───────────────────────────────────────────
        'Leicester City' => 7,
        'FC Rapperswil-Jona' => 4,
        'FC Aarau Frauen' => 4,
    ];

    /**
     * Curated per-club preferred formation. Captures real-world tactical
     * identity so AI opponents feel distinct rather than every club
     * defaulting to 4-3-3. Used by FormationRecommender as a positive
     * bias on formation scoring; the recommender still overrides when
     * squad makeup makes the preferred shape genuinely unviable.
     *
     * Only clubs with a clear tactical identity are listed. Unlisted
     * clubs fall back to a reputation-tier formation pool (see
     * FormationBiasResolver).
     */
    private const PREFERRED_FORMATION_OVERRIDES = [
        // ── Spain — La Liga ──────────────────────────────────────────
        'Real Madrid' => '4-3-1-2',
        'F.C. Barcelona' => '4-2-1-3',
        'Club Atlético de Madrid' => '4-4-2',
        'Athletic Bilbao' => '4-2-1-3',
        'Villarreal CF' => '4-4-2',
        'Real Betis Balompié' => '4-1-2-3',
        'F.C. Sevilla' => '3-4-3',
        'Real Socieadad' => '4-2-1-3',
        'Valencia Féminas Club de Fútbol' => '4-2-1-3',
        'Espanyol Barcelona' => '4-1-2-3',
        'RC Celta' => '3-4-3',
        'RCD Mallorca' => '4-3-1-2',
        'CA Osasuna' => '4-2-1-3',
        'Getafe CF' => '5-3-2',
        'Rayo Vallecano' => '4-2-1-3',
        'Girona FC' => '4-2-1-3',
        'Deportivo Alavés Gloriosas' => '3-5-2',
        'Elche CF' => '5-3-2',
        'Levante UD' => '4-1-2-3',
        'Real Oviedo' => '4-2-1-3',

        // ── Spain — La Liga 2 ────────────────────────────────────────
        'Deportivo A Coruña' => '4-2-3-1',
        'Málaga CF' => '4-2-3-1',
        'Sporting Gijón' => '4-2-3-1',
        'UD Las Palmas' => '4-3-3',
        'Real Valladolid CF' => '4-4-2',
        'Granada CF' => '4-2-3-1',
        'Cádiz CF' => '5-4-1',
        'Racing Santander' => '4-3-3',
        'UD Almería' => '4-2-3-1',
        'Real Zaragoza' => '4-4-2',
        'Córdoba CF' => '4-2-3-1',
        'CD Castellón' => '4-3-3',
        'Albacete Balompié' => '4-2-3-1',
        'SD Huesca' => '4-2-3-1',
        'SD Eibar' => '4-4-2',
        'CD Leganés' => '4-2-3-1',
        'Burgos CF' => '4-4-2',
        'Cultural Leonesa' => '4-4-2',
        'CD Mirandés' => '4-2-3-1',
        'AD Ceuta FC' => '4-4-2',
        'FC Andorra' => '4-3-3',
        'Real Sociedad B' => '4-3-3',

        // ── England ──────────────────────────────────────────────────
        'Manchester City LFC' => '4-3-3',             // Guardiola
        'Liverpool LFC' => '4-3-3',
        'Arsenal FC' => '4-3-3',                  // Arteta
        'Chelsea LFC' => '4-2-3-1',
        'Manchester United' => '3-4-3',           // Amorim
        'Tottenham Hotspur LFC' => '4-3-3',
        'Newcastle United' => '4-3-3',
        'Aston Villa LFC' => '4-2-3-1',               // Emery
        'West Ham United LFC' => '4-2-3-1',
        'Everton LFC' => '4-2-3-1',
        'Brighton & Hove Albion WFC' => '4-2-3-1',
        'Crystal Palace LFC' => '3-4-3',              // Glasner
        'Wolverhampton Wanderers' => '3-4-3',
        'Leeds United' => '4-2-3-1',
        'Nottingham Forest' => '4-2-3-1',
        'Fulham FC' => '4-2-3-1',
        'Brentford FC' => '4-3-3',
        'AFC Bournemouth' => '4-2-3-1',
        'Sunderland AFC' => '4-2-3-1',
        'Burnley FC' => '4-4-2',

        // ── Germany ──────────────────────────────────────────────────
        'Bayern München' => '4-2-3-1',
        'Borussia Dortmund' => '4-2-3-1',
        'Bayer Leverkusen' => '3-4-3',         // Alonso shape
        'Eintracht Frankfurt' => '3-4-3',
        'Rasenballsport Leipzig' => '4-2-3-1',
        '1. FC Union Berlin' => '5-3-2',           // Compact block
        'FC St. Pauli' => '3-4-3',
        '1.FC Heidenheim 1846' => '4-4-2',

        // ── France ───────────────────────────────────────────────────
        'Paris Saint-Germain' => '4-3-3',         // Luis Enrique
        'Olympique de Marseille' => '4-2-3-1',       // De Zerbi
        'RC Lens' => '3-4-3',
        'Stade Brestois 29' => '4-4-2',

        // ── Italy ────────────────────────────────────────────────────
        'Inter Mailand' => '3-5-2',                 // Inzaghi trademark
        'Juventus Football Club' => '4-2-3-1',
        'ACF Mailand' => '4-2-3-1',
        'ASD Napoli Femminile' => '4-3-3',                  // Conte 4-3-3 base
        'Atalanta BC' => '3-4-3',                 // Gasperini
        'AS Rom' => '3-4-3',
        'SS Lazio' => '4-3-3',
        'ACF Florenz' => '4-2-3-1',
        'Bologna FC 1909' => '4-2-3-1',
        'Torino FC' => '3-5-2',
        'Genoa CFC' => '3-5-2',
        'Udinese Calcio' => '3-5-2',
        'Cagliari Calcio' => '4-4-2',
        'Hellas Verona' => '3-4-3',
        'US Cremonese' => '3-5-2',

        // ── Portugal ─────────────────────────────────────────────────
        'SL Benfica' => '4-3-3',
        'FC Porto' => '4-2-3-1',
        'Sporting Clube de Portugal' => '3-4-3',                 // Amorim legacy shape
        'Sporting Clube de Braga Feminino' => '4-4-2',
        'Vitória Sport Clube Guimarães' => '4-2-3-1',

        // ── Netherlands ──────────────────────────────────────────────
        // The Dutch school is 4-3-3 almost without exception.
        'Ajax Amsterdam' => '4-3-3',
        'Feyenoord Rotterdam' => '4-3-3',
        'FCE/PSV' => '4-3-3',
        'AZ Alkmaar' => '4-3-3',
        'FC Twente' => '4-3-3',
        'FC Utrecht' => '4-3-3',
        'Go Ahead Eagles' => '4-2-3-1',

        // ── European pool ────────────────────────────────────────────
        'Celtic FC' => '4-3-3',

        // ── International pool ───────────────────────────────────────
        'CA Boca Juniors' => '4-3-3',
        'CA River Plate' => '4-3-3',
        'CR Flamengo' => '4-2-3-1',
        'SE Palmeiras' => '4-3-3',
        'Al-Hilal SFC' => '4-3-3',
    ];

    /**
     * Curated per-club tactical aggression on a -2..+2 scale. Captures
     * how much more attacking (or more cautious) a club is than its
     * reputation tier alone would suggest. Used by LineupService to
     * shift mentality, pressing, defensive line, and playing style
     * one or two notches up/down the ladder.
     *
     *   +2 — extreme front-foot (Gasperini's Atalanta, peak Pep)
     *   +1 — high-press, attacking by default
     *    0 — tier-typical (the implicit default)
     *   -1 — pragmatic / cautious
     *   -2 — deep low-block (Simeone, Bordalás)
     *
     * Only clubs whose tactical identity meaningfully diverges from
     * the tier-typical baseline need an entry; everyone else stays at 0.
     */
    private const TACTICAL_AGGRESSION_OVERRIDES = [
        // ── Spain — La Liga ──────────────────────────────────────────
        'F.C. Barcelona' => 1,                      // Flick possession-press
        'Club Atlético de Madrid' => -2,               // Cholismo trademark
        'Athletic Bilbao' => 1,                     // Valverde aggressive press
        'Real Socieadad' => 1,                     // Imanol high-tempo
        'Valencia Féminas Club de Fútbol' => -1,
        'Espanyol Barcelona' => -1,
        'RC Celta' => 1,                          // Giráldez attacking
        'RCD Mallorca' => -2,                     // Aguirre block
        'CA Osasuna' => -1,
        'Getafe CF' => -2,                        // Bordalás compact
        'Rayo Vallecano' => 1,                    // Iñigo Pérez attacking
        'Girona FC' => 1,                         // Míchel possession
        'Deportivo Alavés Gloriosas' => -1,
        'Real Oviedo' => -1,

        // ── Spain — La Liga 2 ────────────────────────────────────────
        'UD Las Palmas' => 1,                     // Possession identity
        'Cádiz CF' => -2,                         // Survival deep block
        'Racing Santander' => 1,
        'Córdoba CF' => 1,
        'CD Castellón' => 1,
        'SD Eibar' => -1,                         // Compact identity
        'CD Leganés' => -1,
        'AD Ceuta FC' => -1,
        'FC Andorra' => 1,                        // Eder Sarabia possession
        'Real Sociedad B' => 1,                   // Mirrors first team

        // ── England ──────────────────────────────────────────────────
        'Manchester City LFC' => 2,                   // Pep extreme press
        'Liverpool LFC' => 1,
        'Arsenal FC' => 1,                        // Arteta front-foot
        'Tottenham Hotspur LFC' => 1,
        'Newcastle United' => 1,                  // Howe pressing
        'Brighton & Hove Albion WFC' => 1,            // Progressive system
        'Everton LFC' => -1,
        'Nottingham Forest' => -1,

        // ── Germany ──────────────────────────────────────────────────
        'Bayern München' => 1,
        'Borussia Dortmund' => 1,
        'Bayer Leverkusen' => 1,               // Alonso possession-press
        'Rasenballsport Leipzig' => 1,                        // Red Bull press
        '1. FC Union Berlin' => -2,                // Compact 5-3-2
        'FC Augsburg' => -1,
        '1.FC Heidenheim 1846' => -1,

        // ── France ───────────────────────────────────────────────────
        'Paris Saint-Germain' => 1,               // Luis Enrique press
        'Olympique de Marseille' => 1,               // De Zerbi
        'RC Lens' => 1,
        'Angers SCO' => -1,

        // ── Italy ────────────────────────────────────────────────────
        'ASD Napoli Femminile' => 1,                        // Conte intense
        'Atalanta BC' => 2,                       // Gasperini all-out
        'Bologna FC 1909' => 1,                   // Italiano
        'Cagliari Calcio' => -1,
        'Como 1907' => 1,                         // Fabregas progressive

        // ── Portugal ─────────────────────────────────────────────────
        'SL Benfica' => 1,
        'Sporting Clube de Portugal' => 1,

        // ── Netherlands ──────────────────────────────────────────────
        'Ajax Amsterdam' => 1,
        'Feyenoord Rotterdam' => 1,
        'FCE/PSV' => 1,
        'AZ Alkmaar' => 1,

        // ── European pool ────────────────────────────────────────────
        'Celtic FC' => 1,
        'Red Bull Salzburg' => 1,                 // Red Bull press

        // ── International pool ───────────────────────────────────────
        'CA River Plate' => 1,                    // Gallardo attacking
        'Al-Hilal SFC' => 1,
        'Inter Miami CF' => 1,
    ];

    /**
     * Club names that have curated editorial data (reputation, fan loyalty,
     * preferred formation, tactical aggression). Lookups are by exact name, so
     * a club absent from here silently falls back to a local-reputation profile
     * — wrong for, say, a first-time Champions League qualifier. Exposed so
     * app:validate-season can flag the gap when a season's data is refreshed;
     * alias spellings count as covered, since they resolve to a curated entry.
     *
     * @return array<int, string>
     */
    public static function profiledClubNames(): array
    {
        return array_merge(array_keys(self::CLUB_DATA), ClubNames::aliases());
    }

    public function run(): void
    {
        $allTeams = Team::all();
        $seeded = 0;

        foreach ($allTeams as $team) {
            // National teams key on fifa_code (locale-independent and stable),
            // because Team::name applies the countries.* translation accessor
            // for type='national' rows and would otherwise miss the lookup.
            $isNational = $team->getRawOriginal('type') === 'national';
            $rawName = $team->getRawOriginal('name');
            $fifaCode = $team->fifa_code;

            if ($isNational && $fifaCode) {
                $reputation = self::NATIONAL_TEAM_REPUTATION[$fifaCode] ?? ClubProfile::REPUTATION_LOCAL;
                $preferredFormation = self::NATIONAL_TEAM_PREFERRED_FORMATION[$fifaCode] ?? null;
                $tacticalAggression = self::NATIONAL_TEAM_TACTICAL_AGGRESSION[$fifaCode] ?? 0;
                // Fan loyalty doesn't apply to national teams (no club fanbase
                // demand curve), so we leave it at the neutral default.
                $fanLoyalty = ClubProfile::FAN_LOYALTY_DEFAULT;
            } else {
                // Resolve renamed clubs to their curated key first: rows seeded
                // before a re-spelling still hold the old name, and both must
                // land on the same profile.
                $lookupName = ClubNames::canonical($rawName);

                $reputation = self::CLUB_DATA[$lookupName] ?? ClubProfile::REPUTATION_LOCAL;
                $fanLoyalty = self::FAN_LOYALTY_OVERRIDES[$lookupName]
                    ?? ClubProfile::FAN_LOYALTY_DEFAULT;
                $preferredFormation = self::PREFERRED_FORMATION_OVERRIDES[$lookupName] ?? null;
                $tacticalAggression = self::TACTICAL_AGGRESSION_OVERRIDES[$lookupName] ?? 0;
            }

            ClubProfile::updateOrCreate(
                ['team_id' => $team->id],
                [
                    'reputation_level' => $reputation,
                    'fan_loyalty' => $fanLoyalty,
                    'preferred_formation' => $preferredFormation,
                    'tactical_aggression' => $tacticalAggression,
                ]
            );

            $seeded++;
        }

        $this->command->info('Club profiles seeded for ' . $seeded . ' teams');
    }
}
