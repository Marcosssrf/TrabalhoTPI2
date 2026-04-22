Trabalho TPI2

Comandos Usados

# 1. Iniciar e primeiro commit
git init
git add .
git commit -m "feat: estrutura inicial do projeto"

# 2. Conectar ao GitHub
git remote add origin https://github.com/Marcosssrf/TrabalhoTPI2.git
git branch -M main
git push -u origin main

# 4. Criar branches dos alunos
git checkout -b marcos
git checkout -b otavio
git checkout -b pitu
git checkout -b vhmazza

# 5. Cada aluno commita sua branch
git checkout branch-aluno
git add .
git commit -m "Mensagem"
git push

# 6. Baixar as branches localmente
git fetch
git checkout -b marcos origin/marcos
git checkout -b pitu origin/pitu
git checkout -b otavio origin/otavio
git checkout -b vhmazza origin/vhmazza

# 7. Voltar para main e fazer merge
git checkout main
git merge marcos --no-ff -m "merge: marcos"
git merge pitu --no-ff -m "merge: pitu"
git merge otavio --no-ff -m "merge: otavio"
git merge vhmazza --no-ff -m "merge: vhmazza"
git push origin main