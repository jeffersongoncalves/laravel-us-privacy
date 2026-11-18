<?php

namespace JeffersonGoncalves\UsPrivacy\Enums;

/**
 * Kinds of sensitive data in the state laws. Every state lists the first ones; the
 * others appear in some states only (noted), which the inventory records as declared.
 */
enum SensitiveType: string
{
    case RacialOrEthnicOrigin = 'racial_or_ethnic_origin';
    case ReligiousBeliefs = 'religious_beliefs';
    case Health = 'health';                                 // mental or physical health condition or diagnosis
    case SexLifeOrOrientation = 'sex_life_or_orientation';
    case CitizenshipOrImmigration = 'citizenship_or_immigration';
    case Genetic = 'genetic';
    case Biometric = 'biometric';                           // processed to uniquely identify a person
    case KnownChild = 'known_child';                        // personal data of a known child under 13
    case PreciseGeolocation = 'precise_geolocation';        // within a 1,750-foot radius
    case NationalOrigin = 'national_origin';                // e.g. Oregon, Delaware, Maryland, New Jersey
    case TransgenderOrNonbinaryStatus = 'transgender_or_nonbinary_status'; // e.g. Oregon, Delaware, Maryland, New Jersey, Connecticut
    case CrimeVictimStatus = 'crime_victim_status';         // e.g. Oregon, Connecticut
    case FinancialAccount = 'financial_account';            // e.g. New Jersey, Connecticut: account number with access code
    case GovernmentIdentifier = 'government_identifier';    // e.g. Connecticut
    case UnionMembership = 'union_membership';              // e.g. New Jersey, Maryland
    case NeuralData = 'neural_data';                        // e.g. Colorado, Connecticut, Montana
    case ConsumerHealth = 'consumer_health';                // consumer health data, e.g. Connecticut, Maryland
}
