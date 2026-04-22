<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../utils/utils.php';

use Ramsey\Uuid\Uuid;

class RDA
{
       
    public function __construct()
    {
        // Constructor vacío
    }
    
    /**
     * Transforma los datos de entrada al formato FHIR Patient
     * 
     * @param array $inputData Datos recibidos del cliente
     * @return array Datos transformados al formato FHIR
     */
    public function transform($inputData)
    {
        // Separamos el json en partes
        $pacienteData = $inputData['paciente'];
        $profesionalData = $inputData['profesional'];
        $organizacionData = $inputData['organizacion'];
        $diagnosticoData = $inputData['diagnosticos'];
        $medicacionData = $inputData['medicamentos'];
        $alergiaData = $inputData['alergias'];

        //Generamos un UUID
        $ListaUUID = Uuid::uuid4()->toString();
        $DocumentUUID = Uuid::uuid4()->toString();
        $BundleUUID = Uuid::uuid4()->toString();
        $BundleTransactionUUID = Uuid::uuid4()->toString();
        $CompositionId = Uuid::uuid4()->toString();

        // Aquí deberías construir tus recursos FHIR basados en los datos obtenidos
        $entryArray = []; // Construye tus entries FHIR aquí
        $entryBundleArray = []; // Entries para el Bundle DocumentReference
        $sections = []; // Secciones para el Composition
        $conditionArray = []; // Entradas para la sección de Diagnósticos
        $condition = []; // Recursos Condition completos para el Bundle
        $medicationsArray = []; // Entradas para la sección de Medicamentos
        $medication = []; // Recursos MedicationStatement completos para el Bundle
        $allergiesArray = []; // Entradas para la sección de Alergias
        $alergy = []; // Recursos AllergyIntolerance completos para el Bundle
        $composition = []; // Recurso Composition para el Bundle
        $patient = []; // Recurso Patient para el Bundle
        $practitioner = []; // Recurso Practitioner para el Bundle
        $organizacion = []; // Recurso Organization para el Bundle

        //Buscamos el ID del paciente en el FHIR Patient
        $buscarPaciente = buscarPacientePorCedula($pacienteData['documento']);

        //Decodificamos el resultado de la búsqueda para obtener el ID del paciente
        $pacienteId = $buscarPaciente['patient']['id'];
        
        // Construir lista de given (nombres)
        $given = [];
        if (!empty($pacienteData['primer_nombre'])) {
            $given[] = $pacienteData['primer_nombre'];
        }
        if (!empty($pacienteData['segundo_nombre'])) {
            $given[] = $pacienteData['segundo_nombre'];
        }

        // Construir el apellido (family) solo con los que existan
        $familyParts = [];
        if (!empty($pacienteData['primer_apellido'])) {
            $familyParts[] = $pacienteData['primer_apellido'];
        }
        if (!empty($pacienteData['segundo_apellido'])) {
            $familyParts[] = $pacienteData['segundo_apellido'];
        }

        $nombreCompleto = implode(" ", $given) . " " . implode(" ", $familyParts);

        $gender = "unknown";
        if ($pacienteData['sexo'] == "masculino") $gender = "male";
        if ($pacienteData['sexo'] == "femenino") $gender = "female";
        if ($pacienteData['sexo'] == "otro") $gender = "other";
        if ($pacienteData['sexo'] == "desconocido") $gender = "unknown";

        $display = '';
        if($pacienteData['tipo_documento'] == '01'){
            $display = "Cédula de Identidad";
        } elseif($pacienteData['tipo_documento'] == '02'){
            $display = "Cédula Extranjera";
        } else {
            $display = "Pasaporte";
        }




        //Buscamos el ID del profesional en el FHIR Practitioner
        $buscarProfesional = buscarPractitionerPorCedula($profesionalData['documento']);

        //Decodificamos el resultado de la búsqueda para obtener el ID del profesional
        $profesionalId = $buscarProfesional['practitioner']['id'];

        $givenProfesional = [];
        if (!empty($profesionalData['primer_nombre'])) {
            $givenProfesional[] = $profesionalData['primer_nombre'];
        }
        if (!empty($profesionalData['segundo_nombre'])) {
            $givenProfesional[] = $profesionalData['segundo_nombre'];
        }

        $familyPartsProfesional = [];
        if (!empty($profesionalData['primer_apellido'])) {
            $familyPartsProfesional[] = $profesionalData['primer_apellido'];
        }
        if (!empty($profesionalData['segundo_apellido'])) {
            $familyPartsProfesional[] = $profesionalData['segundo_apellido'];
        }

        $nombreCompletoProfesional = implode(" ", $givenProfesional) . " " . implode(" ", $familyPartsProfesional);

        //Recorremos los diagnósticos para construir el array de condiciones
        foreach ($diagnosticoData as $diagnostico) {

            $diagnosticoUUID = Uuid::uuid4()->toString();
            
            // Agregar la referencia a la sección
            $conditionArray[] = [
                "reference" => "urn:uuid:" . $diagnosticoUUID
            ];

            $condition[] = 
             [
                "fullUrl" => "urn:uuid:".$diagnosticoUUID,
                "resource" => [
                    "resourceType" => "Condition",
                    "id"           => $diagnosticoUUID,
                    "meta" => [
                        "profile"  => ["https://mspbs.gov.py/fhir/StructureDefinition/ConditionPy"]
                    ],
                    "text" => [
                        "status" => "generated",
                        "div"    => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><h1>Condition Example</h1></div>"
                    ],
                    "verificationStatus" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/condition-ver-status",
                                "code" => $diagnostico['status'],
                            ]
                        ]
                    ],
                    "code" => [
                        "coding" => [
                            [
                                "system" => "http://hl7.org/fhir/sid/icd-10",
                                "code" => $diagnostico['cie10_code'],
                                "display" => $diagnostico['cie10_term']   
                            ]
                        ],
                        "text" => $diagnostico['cie10_term']
                    ],
                    "subject" => [
                        "reference" => "urn:uuid:".$pacienteId
                    ],
                    "onsetPeriod" => [
                         "start" => date('Y-m-d\TH:i:s', strtotime($diagnostico['diagnostico_fecha']))
                    ],
                    "note" => [
                        [
                            "text" => $diagnostico['nota']
                        ]
                    ]                                    
                ]

            ];
        }

        //Recorremos las alergias para construir el array de alergias
        foreach ($alergiaData as $alergia) {

            $alergiaUUID = Uuid::uuid4()->toString();

            // Agregar la referencia a la sección
            $allergiesArray[] = [
                "reference" => "urn:uuid:" . $alergiaUUID
            ];

            $category = '';
            if($alergia['categoria'] == 'medicamento'){
                $category = 'medication';
            } elseif($alergia['categoria'] == 'alimento'){
                $category = 'food';
            } elseif($alergia['categoria'] == 'animal'){
                $category = 'environment';
            } else {
                $category = 'environment';
            }

            $alergy[] = 
            [
                "fullUrl" => "urn:uuid:".$alergiaUUID,
                "resource" => [
                    "resourceType" => "AllergyIntolerance",
                    "id"           => $alergiaUUID,
                    "meta" => [
                        "profile"  => ["https://mspbs.gov.py/fhir/StructureDefinition/AlergiaPy"]
                    ],
                    "text" => [
                        "status" => "generated",
                        "div"    => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><h1>Alergia Example</h1></div>"
                    ],
                    "clinicalStatus" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                                "code" => "active"
                            ]
                        ]
                    ],
                    "verificationStatus" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                                "code" => "confirmed"
                            ]
                        ]
                    ],
                    "category" => [
                        $category
                    ],
                    "code" => [                        
                        "text" => $alergia['alergia_term']
                    ],
                    "patient" => [
                        "reference" => "urn:uuid:".$pacienteId
                    ]
                ]
            ];
        }

        //Recorremos los medicamentos para construir el array de medicamentos
        foreach ($medicacionData as $medicacion) {
            $medicacionUUID = Uuid::uuid4()->toString();

            // Agregar la referencia a la sección
            $medicationsArray[] = [
                "reference" => "urn:uuid:" . $medicacionUUID
            ];
            $fechaMedicamento = !empty($medicacion['medicamento_fecha']) 
            ? date('Y-m-d\TH:i:s', strtotime($medicacion['medicamento_fecha']))
            : date('Y-m-d\TH:i:s');

            $medication[] = 
             [
                "fullUrl" => "urn:uuid:". $medicacionUUID,
                "resource" => [
                    "resourceType" => "MedicationStatement",
                    "id" => $medicacionUUID,
                    "meta" => [
                        "profile" => ["https://mspbs.gov.py/fhir/StructureDefinition/MedicationStatementPy"]
                    ],
                    "text" => [
                        "status" => "generated",
                        "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><h1>Medication Example</h1></div>"
                    ],
                    "status" => "active",
                    "medicationCodeableConcept" => [
                        "text" => $medicacion['medicamento_term']
                    ],
                    "subject" => [
                        "reference" => "urn:uuid:".$pacienteId
                    ],
                    "effectiveDateTime" => $fechaMedicamento, 
                    "dosage" => [
                        [
                            "text" => $medicacion['medicamento_dosis'],
                            "route" => [
                                "text" => $medicacion['medicamento_via']
                            ]
                        ]
                    ]
                ]
            ];
        }



        //Construimos el List
        $entryArray[] = 
        [
            "fullUrl"  => "urn:uuid:$ListaUUID",
            "resource" => [
                "resourceType" => "List",
                "id"           => $ListaUUID,
                "meta" => [
                    "profile"  => ["https://mspbs.gov.py/fhir/StructureDefinition/ListPy"]
                ],
                "text" => [
                    "status" => "extensions",
                    "div"    => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><a name=\"List_ListEjemploPy2\"> </a><p class=\"res-header-id\"><b>Generated Narrative: List ListEjemploPy2</b></p><a name=\"ListEjemploPy2\"> </a><a name=\"hcListEjemploPy2\"> </a><div style=\"display: inline-block; background-color: #d9e0e7; padding: 6px; margin: 4px; border: 1px solid #8da1b4; border-radius: 5px; line-height: 60%\"><p style=\"margin-bottom: 0px\"/><p style=\"margin-bottom: 0px\">Profile: <a href=\"StructureDefinition-ListPy.html\">ListPy</a></p></div><table class=\"clstu\"><tr><td>Date: 2025-09-01 10:30:00+0000 </td><td>Mode: Working List </td><td>Status: Current </td></tr><tr><td>Subject: <a href=\"Bundle-BundleTrancEjemploPy.html#urn-uuid-05d3374b-0278-4d04-93f7-6adc181d5874\">Bundle: type = transaction; timestamp = 2022-03-03 10:30:00+0000</a></td></tr></table><table class=\"grid\"><tr style=\"backgound-color: #eeeeee\"><td><b>Items</b></td></tr><tr><td><a href=\"Bundle-BundleTrancEjemploPy.html#urn-uuid-487b6713-4647-4a9a-914e-7c552d7197e9\">Bundle: type = transaction; timestamp = 2022-03-03 10:30:00+0000</a></td></tr></table></div>"
                ],                
                "status" => "current",
                "mode"   => "working",
                "subject"=> ["reference" => "urn:uuid:".$pacienteId],
                "date"   => date('c'),
                "entry"  => [[ "item" => ["reference" => "urn:uuid:".$DocumentUUID] ]]
               ],
               "request" => [ "method" => "POST", "url" => "List" ]

        ];

         // Construimos el DocumentReference
        $entryArray[] = 
        [
            "fullUrl"  => "urn:uuid:".$DocumentUUID,
            "resource" => [
                "resourceType" => "DocumentReference",
                "id"           => $DocumentUUID,
                "meta" => [
                    "profile"  => ["https://mspbs.gov.py/fhir/StructureDefinition/DocumentReferencePy"]
                ],
                "text" => [
                    "status" => "generated",
                    "div"    => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><a name=\"DocumentReference_DocumentReferenceEjemploPy2\"> </a><p class=\"res-header-id\"><b>Generated Narrative: DocumentReference DocumentReferenceEjemploPy2</b></p><a name=\"DocumentReferenceEjemploPy2\"> </a><a name=\"hcDocumentReferenceEjemploPy2\"> </a><div style=\"display: inline-block; background-color: #d9e0e7; padding: 6px; margin: 4px; border: 1px solid #8da1b4; border-radius: 5px; line-height: 60%\"><p style=\"margin-bottom: 0px\"/><p style=\"margin-bottom: 0px\">Profile: <a href=\"StructureDefinition-DocumentReferencePy.html\">Referencia de Documentos</a></p></div><p><b>status</b>: Current</p><p><b>type</b>: <span title=\"Codes:{http://loinc.org 34105-7}\">Nota de consulta</span></p><p><b>subject</b>: <a href=\"Bundle-BundleTrancEjemploPy.html#urn-uuid-05d3374b-0278-4d04-93f7-6adc181d5874\">Bundle: type = transaction; timestamp = 2022-03-03 10:30:00+0000</a></p><p><b>date</b>: 2025-09-01 10:30:00+0000</p><p><b>author</b>: <code>PractitionerPy/PractitionerEjemploPy</code></p><p><b>custodian</b>: <a href=\"Organization-OrganizacionEjemploPy.html\">Organization HOSPITAL GENERAL DE CORONEL OVIEDO</a></p><blockquote><p><b>content</b></p><h3>Attachments</h3><table class=\"grid\"><tr><td style=\"display: none\">-</td><td><b>ContentType</b></td><td><b>Url</b></td></tr><tr><td style=\"display: none\">*</td><td>application/fhir+json</td><td><a href=\"Bundle-BundleTrancEjemploPy.html#urn-uuid-d384326c-7c0f-4ac2-ba90-a1d83e5b548f\">Bundle: type = transaction; timestamp = 2022-03-03 10:30:00+0000</a></td></tr></table></blockquote></div>"
                ],
                "status"  => "current",
                "subject" => ["reference" => "urn:uuid:".$pacienteId],
                 "type" => [
                    "coding" => [
                        [
                            "system" => "http://loinc.org", 
                            "code" => "34105-7",
                            "display" => "Nota de consulta"
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => "urn:uuid:".$pacienteId
                ],
                "date" => date('c'),
                "author" => [[
                    "reference" => "Practitioner/".$profesionalId
                ]],
                "custodian" => [
                    "reference" => "Organization/".$organizacionData['codigo']
                ], 
                "content" => [[
                    "attachment" => [
                         "contentType" => "application/fhir+json",
                         "url" => "urn:uuid:". $BundleUUID,
                         "title" => "Individual Patient Summary"
                    ]
                ]]
            ],
            "request" => [ "method" => "POST", "url" => "DocumentReference" ]
        ];

         // Añadimos las secciones al Composition
        // Sección Diagnósticos
        $sections[] =
        [
            "title" => "Active Problems",
            "code" => [
                "coding" => [[
                    "system" => "http://loinc.org",
                    "code" => "11450-4",
                    "display" => "Problem list Reported"
                ]]
            ],
            "text" => [
                "status" => "generated",
                "div" => "<div xmlns='http://www.w3.org/1999/xhtml'>Resumen de problemas activos actuales del paciente.</div>"
            ],
            "entry" => $conditionArray
        ];

        // Sección Alergias
        $sections[] =
        [
            "title" => "Allergies and Intolerances",
            "code" => [
                "coding" => [[
                    "system" => "http://loinc.org",
                    "code" => "48765-2",
                    "display" => "Allergies and adverse reactions Document"
                ]]
            ],
            "text" => [
                "status" => "generated",
                "div" => "<div xmlns='http://www.w3.org/1999/xhtml'>Resumen de alergias e intolerancias registradas.</div>"
            ],
            "entry" => $allergiesArray
        ];

        // Sección Medicamentos
        $sections[] =
        [
            "title" => "MedicationStatement",
            "code" => [
                "coding" => [[
                    "system" => "http://loinc.org",
                    "code" => "10160-0",
                    "display" => "History of Medication use Narrative"
                ]]
            ],
            "text" => [
                "status" => "generated",
                "div" => "<div xmlns='http://www.w3.org/1999/xhtml'>Historial de uso de medicación reportado.</div>"
            ],
            "entry" => $medicationsArray
        ];
        // Agregamos el Composition al entryBundleArray
        $composition[] = 
        [
            "fullUrl" => "urn:uuid:" . $CompositionId,
            "resource" => [
                "resourceType" => "Composition",
                "id" => $CompositionId,
                "meta" => [
                    "profile" => ["https://mspbs.gov.py/fhir/StructureDefinition/CompositionPy"]
                ],
                "text" => [
                    "status" => "generated",
                    "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><h1>Documento Resumen Clínico de Paciente de Paraguay</h1></div>"
                ],
                "status" => "final",
                "type" => [
                    "coding" => [[
                        "system" => "http://loinc.org",
                        "code" => "60591-5",
                        "display" => "Patient Summary Document"
                    ]]
                ],
                "subject" => [
                    "reference" => "urn:uuid:" . $pacienteId
                ],
                "date" => date('c'),
                "author" => [[
                    "reference" => "urn:uuid:" . $profesionalId
                ]],
                "title" => "Documento Clinico Paraguay de " . date('d/m/Y'),
                "confidentiality" => "N",
                "custodian" => [
                    "reference" => "urn:uuid:" . $organizacionData['codigo']
                ],
                "section" => $sections
            ]
        ];

        //Agregamos el Patient al Bundle FHIR
        $patient[] = 
        [
            "fullUrl" => "urn:uuid:".$pacienteId,
            "resource" => [
                "resourceType" => "Patient",
                "id"           => $pacienteId,
                "meta" => [
                    "profile"  => ["https://mspbs.gov.py/fhir/StructureDefinition/PacientePy"]
                ],
                "text" => [
                    "status" => "generated",
                    "div"    => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><a name=\"Patient_PacienteEjemploPy\"> </a><p class=\"res-header-id\"><b>Generated Narrative: Patient PacienteEjemploPy</b></p><a name=\"PacienteEjemploPy\"> </a><a name=\"hcPacienteEjemploPy\"> </a><div style=\"display: inline-block; background-color: #d9e0e7; padding: 6px; margin: 4px; border: 1px solid #8da1b4; border-radius: 5px; line-height: 60%\"><p style=\"margin-bottom: 0px\"/><p style=\"margin-bottom: 0px\">Profile: <a href=\"StructureDefinition-PacientePy.html\">Paciente Paraguay</a></p></div><p style=\"border: 1px #661aff solid; background-color: #e6e6ff; padding: 10px;\">".$nombreCompleto." ".$gender.", DoB: " . $pacienteData['fecha_nacimiento'] . " ( Cédula de Identidad: ". $pacienteData['documento'].")</p><hr/></div>"
                ],
                "identifier" => [
                    [
                        "type" => [
                            "coding" => [
                                [
                                    "system" => "https://mspbs.gov.py/fhir/CodeSystem/IdentificadoresPersonaCS",
                                    "code" => $pacienteData['tipo_documento'],
                                    "display" => $display
                                ]
                            ]
                        ],
                        "value" => $pacienteData['documento']
                    ]
                ],
                "name" => [
                    [
                        "family" => implode(" ", $familyParts),
                        "given" => $given
                    ]
                ],
                "gender" => $gender,
                "birthDate" => $pacienteData['fecha_nacimiento']
            ]
        ];

        //Agregamos el Practitioner
        $practitioner[] =
        [
            "fullUrl" => "urn:uuid:" . $profesionalId,
            "resource" => [
                "resourceType" => "Practitioner",
                "id" => $profesionalId,
                "meta" => [
                    "profile" => [
                        "https://mspbs.gov.py/fhir/StructureDefinition/PractitionerPy"
                    ]
                ],
                "text" => [
                    "status" => "generated",
                    "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><p class=\"res-header-id\"><b>Generated Narrative: Practitioner PractitionerEjemploPy</b></p><a name=\"PractitionerEjemploPy\"> </a><a name=\"hcPractitionerEjemploPy\"> </a><div style=\"display: inline-block; background-color: #d9e0e7; padding: 6px; margin: 4px; border: 1px solid #8da1b4; border-radius: 5px; line-height: 60%\"><p style=\"margin-bottom: 0px\"/><p style=\"margin-bottom: 0px\">Profile: <a href=\"StructureDefinition-PractitionerPy.html\">Profesional Paraguay</a></p></div><p><b>identifier</b>: Cédula de Identidad/" . $profesionalData['documento'] . "</p><p><b>name</b>: ".$nombreCompletoProfesional." </p></div>"
                ],
                "identifier" => [
                    [
                        "type" => [
                            "coding" => [
                                [
                                    "system" => "https://mspbs.gov.py/fhir/CodeSystem/IdentificadoresProfesionalCS",
                                    "code" => "01",
                                    "display" => "Cédula de Identidad"
                                ]
                            ]
                        ],
                        "value" => trim($profesionalData['documento'])
                    ]
                ],
                "name" => [
                    [
                        "family" => implode(" ", $familyPartsProfesional),
                        "given" => $givenProfesional
                    ]
                ]               
            ]
        ];

        //Agregamos el Organization
        $organization[] =
        [
            "fullUrl"=> "urn:uuid:". $organizacionData['codigo'],
            "resource"=> [
                "resourceType"=> "Organization",
                "id"=> $organizacionData['codigo'],
                "meta" => [
                    "profile" => [ "https://mspbs.gov.py/fhir/StructureDefinition/OrganizacionPy" ]
                ],
                "text" => [
                    "status" => "generated",
                    "div" => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><p class=\"res-header-id\"><b>Generated Narrative: Organization OrganizacionEjemploPy</b></p><a name=\"OrganizacionEjemploPy\"> </a><a name=\"hcOrganizacionEjemploPy\"> </a><div style=\"display: inline-block; background-color: #d9e0e7; padding: 6px; margin: 4px; border: 1px solid #8da1b4; border-radius: 5px; line-height: 60%\"><p style=\"margin-bottom: 0px\"/><p style=\"margin-bottom: 0px\">Profile: <a href=\"StructureDefinition-OrganizacionPy.html\">Organizacion Paraguay</a></p></div><p><b>identifier</b>: 0005000.00010102</p><p><b>type</b>: <span title=\"Codes:\">HG</span></p><p><b>name</b>: HOSPITAL GENERAL DE CORONEL OVIEDO</p></div>"
                ],
                "identifier" => [
                    [
                        "value" => $organizacionData['codigo']
                    ]
                ],
                "type" => [
                    [
                        "text" => $organizacionData['tipo']
                    ]
                ],
                "name" => $organizacionData['nombre']
            ]
        ];

        // Construimos el Bundle FHIR
        $entryArray[] = 
        [
            "fullUrl"=> "urn:uuid:".$BundleUUID,
            "resource"=> [
                "resourceType"=> "Bundle",
                "id"=> $BundleUUID,
                "meta" => [
                    "profile" => [
                         "https://mspbs.gov.py/fhir/StructureDefinition/BundleDocPy",
                         "https://mspbs.gov.py/fhir/StructureDefinition/BundlePy"
                    ]
                ],
                "identifier"=> [
                    "system"=> "urn:oid",
                    "value"=> $BundleUUID
                ],
                "type"=> "document",
                "timestamp"=> date('c'),
                "entry"=> array_merge($composition, $patient, $condition, $alergy, $medication, $practitioner, $organization)
            ],
            "request"=> [
                "method"=> "POST",
                "url"=> "Bundle"
            ]
        ];

        //Agregamos el Patient
        $entryArray[] = 
        [
            "fullUrl" => "urn:uuid:".$pacienteId,
            "resource" => [
                "resourceType" => "Patient",
                "id"           => $pacienteId,
                "meta" => [
                    "profile"  => ["https://mspbs.gov.py/fhir/StructureDefinition/PacientePy"]
                ],
                "text" => [
                    "status" => "generated",
                    "div"    => "<div xmlns=\"http://www.w3.org/1999/xhtml\"><a name=\"Patient_PacienteEjemploPy\"> </a><p class=\"res-header-id\"><b>Generated Narrative: Patient PacienteEjemploPy</b></p><a name=\"PacienteEjemploPy\"> </a><a name=\"hcPacienteEjemploPy\"> </a><div style=\"display: inline-block; background-color: #d9e0e7; padding: 6px; margin: 4px; border: 1px solid #8da1b4; border-radius: 5px; line-height: 60%\"><p style=\"margin-bottom: 0px\"/><p style=\"margin-bottom: 0px\">Profile: <a href=\"StructureDefinition-PacientePy.html\">Paciente Paraguay</a></p></div><p style=\"border: 1px #661aff solid; background-color: #e6e6ff; padding: 10px;\">".$nombreCompleto."  " . $gender . ", DoB: " . $pacienteData['fecha_nacimiento'] . " ( Cédula de Identidad: " . $pacienteData['documento'] . ")</p><hr/></div>"
                ],
                "identifier" => [
                    [
                        "type" => [
                            "coding" => [
                                [
                                    "system" => "https://mspbs.gov.py/fhir/CodeSystem/IdentificadoresPersonaCS",
                                    "code" => $pacienteData['tipo_documento'],
                                    "display" => $display
                                ]
                            ]
                        ],
                        "value" => $pacienteData['documento']
                    ]
                ],
                "name" => [
                    [
                        "family" => implode(" ", $familyParts),
                        "given" => $given
                    ]
                ],
                "gender" => $gender,
                "birthDate" => $pacienteData['fecha_nacimiento']
            ],
            "request" => [ "method" => "PUT", "url" => "Patient/".$pacienteId ]
        ];           

        // Estructura base del Bundle FHIR
        $bundle = [
            "resourceType" => "Bundle",               
            "meta" => [
                "profile" => [
                    "https://mspbs.gov.py/fhir/StructureDefinition/BundleTransaccPy"
                ]                  
            ],
            "id" => $BundleTransactionUUID,
            "type" => "transaction",
            "timestamp" => date('c'),
            "entry" => $entryArray
        ];

        
      
        
        return $bundle;
    }
}

?>