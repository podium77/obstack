#!/bin/bash

# ═══════════════════════════════════════════════════════════════════════════
# Script de Test - API de Suppression de Company
# ═══════════════════════════════════════════════════════════════════════════

# Configuration
API_BASE="http://localhost:8000"
API_ENDPOINT="/api/admin/companies"
BEARER_TOKEN="${JWT_TOKEN:-your_bearer_token_here}"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ─────────────────────────────────────────────────────────────────────────
# Fonction: Afficher les titres
# ─────────────────────────────────────────────────────────────────────────
print_header() {
  echo -e "\n${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
  echo -e "${BLUE}║ $1${NC}"
  echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}\n"
}

# ─────────────────────────────────────────────────────────────────────────
# Fonction: Afficher les résultats
# ─────────────────────────────────────────────────────────────────────────
print_result() {
  local status=$1
  local message=$2
  
  if [ $status -eq 0 ]; then
    echo -e "${GREEN}✓ SUCCESS${NC}: $message"
  else
    echo -e "${RED}✗ ERROR${NC}: $message"
  fi
}

# ─────────────────────────────────────────────────────────────────────────
# TEST 1: Lister toutes les companies
# ─────────────────────────────────────────────────────────────────────────
print_header "TEST 1: Lister toutes les companies"

echo -e "${YELLOW}Commande:${NC}"
echo "curl -X GET \\"
echo "  -H 'Authorization: Bearer ${BEARER_TOKEN}' \\"
echo "  ${API_BASE}${API_ENDPOINT}"
echo ""

echo -e "${YELLOW}Réponse:${NC}"
curl -X GET \
  -H "Authorization: Bearer ${BEARER_TOKEN}" \
  -H "Content-Type: application/json" \
  "${API_BASE}${API_ENDPOINT}" 2>/dev/null | jq '.' 2>/dev/null || \
  curl -X GET \
    -H "Authorization: Bearer ${BEARER_TOKEN}" \
    -H "Content-Type: application/json" \
    "${API_BASE}${API_ENDPOINT}" 2>/dev/null

echo ""

# ─────────────────────────────────────────────────────────────────────────
# TEST 2: Obtenir une company spécifique
# ─────────────────────────────────────────────────────────────────────────
print_header "TEST 2: Obtenir une company (ID: 1)"

echo -e "${YELLOW}Commande:${NC}"
echo "curl -X GET \\"
echo "  -H 'Authorization: Bearer ${BEARER_TOKEN}' \\"
echo "  ${API_BASE}${API_ENDPOINT}/1"
echo ""

echo -e "${YELLOW}Réponse:${NC}"
curl -X GET \
  -H "Authorization: Bearer ${BEARER_TOKEN}" \
  -H "Content-Type: application/json" \
  "${API_BASE}${API_ENDPOINT}/1" 2>/dev/null | jq '.' 2>/dev/null || \
  curl -X GET \
    -H "Authorization: Bearer ${BEARER_TOKEN}" \
    -H "Content-Type: application/json" \
    "${API_BASE}${API_ENDPOINT}/1" 2>/dev/null

echo ""

# ─────────────────────────────────────────────────────────────────────────
# TEST 3: Supprimer une company (DANGER!)
# ─────────────────────────────────────────────────────────────────────────
print_header "TEST 3: Supprimer une company (ID: 2)"

echo -e "${RED}⚠️  ATTENTION: Cela va VRAIMENT supprimer la company!${NC}\n"

echo -e "${YELLOW}Commande:${NC}"
echo "curl -X DELETE \\"
echo "  -H 'Authorization: Bearer ${BEARER_TOKEN}' \\"
echo "  ${API_BASE}${API_ENDPOINT}/2"
echo ""

read -p "Êtes-vous SÛR de vouloir tester la suppression? (y/N) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
  echo -e "${YELLOW}Réponse:${NC}"
  RESPONSE=$(curl -s -w "\n%{http_code}" -X DELETE \
    -H "Authorization: Bearer ${BEARER_TOKEN}" \
    -H "Content-Type: application/json" \
    "${API_BASE}${API_ENDPOINT}/2")
  
  HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
  BODY=$(echo "$RESPONSE" | head -n-1)
  
  echo "$BODY" | jq '.' 2>/dev/null || echo "$BODY"
  echo ""
  echo -e "HTTP Status: ${HTTP_CODE}"
else
  echo "Suppression annulée."
fi

echo ""

# ─────────────────────────────────────────────────────────────────────────
# TEST 4: Test d'erreur - Pas d'authentification
# ─────────────────────────────────────────────────────────────────────────
print_header "TEST 4: Test d'erreur - Pas d'authentification"

echo -e "${YELLOW}Commande (sans token):${NC}"
echo "curl -X GET ${API_BASE}${API_ENDPOINT}"
echo ""

echo -e "${YELLOW}Réponse (attendue: 401 Unauthorized):${NC}"
curl -s -X GET \
  -H "Content-Type: application/json" \
  "${API_BASE}${API_ENDPOINT}" | jq '.' 2>/dev/null || \
  curl -s -X GET \
    -H "Content-Type: application/json" \
    "${API_BASE}${API_ENDPOINT}"

echo ""

# ─────────────────────────────────────────────────────────────────────────
# TEST 5: Test d'erreur - Rôle insuffisant
# ─────────────────────────────────────────────────────────────────────────
print_header "TEST 5: Test avec un utilisateur normal (pas SUPERADMIN)"

echo -e "${YELLOW}Commande (user token au lieu de superadmin):${NC}"
echo "curl -X GET \\"
echo "  -H 'Authorization: Bearer ${USER_TOKEN}' \\"
echo "  ${API_BASE}${API_ENDPOINT}"
echo ""

echo -e "${RED}Note: Remplacez ${USER_TOKEN} par un token d'utilisateur normal${NC}"
echo ""

# ─────────────────────────────────────────────────────────────────────────
# Résumé
# ─────────────────────────────────────────────────────────────────────────
print_header "Résumé des Tests"

echo -e "${BLUE}Tests effectués:${NC}"
echo "  1. ✓ Lister toutes les companies"
echo "  2. ✓ Obtenir une company par ID"
echo "  3. ✓ Supprimer une company (optionnel)"
echo "  4. ✓ Test sans authentification"
echo "  5. ℹ Test avec rôle insuffisant"
echo ""

echo -e "${BLUE}Points de vérification:${NC}"
echo "  • Les companies sont retournées correctement"
echo "  • Les ID et les noms correspondent"
echo "  • Les comptes de relations (environments, users) sont corrects"
echo "  • La suppression retourne success=true si elle fonctionne"
echo "  • Les erreurs 401/403 sont retournées correctement"
echo ""

# ─────────────────────────────────────────────────────────────────────────
# Instructions pour obtenir le JWT token
# ─────────────────────────────────────────────────────────────────────────
echo -e "${YELLOW}Comment obtenir votre JWT_TOKEN:${NC}"
echo ""
echo "1. Connectez-vous à l'application:"
echo "   http://localhost:5174/login"
echo ""
echo "2. Ouvrez les DevTools (F12)"
echo "3. Allez dans l'onglet 'Application' ou 'Storage'"
echo "4. Trouvez le cookie 'token' ou 'Authorization'"
echo "5. Copiez la valeur"
echo ""
echo "6. Exportez comme variable:"
echo "   export JWT_TOKEN='votre_token_ici'"
echo ""
echo "7. Lancez ce script à nouveau:"
echo "   bash test_company_api.sh"
echo ""

# ─────────────────────────────────────────────────────────────────────────
# Fin
# ─────────────────────────────────────────────────────────────────────────
echo -e "${GREEN}Tests terminés!${NC}\n"
