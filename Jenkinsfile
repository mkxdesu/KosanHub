pipeline {
    agent any

    options {
        skipDefaultCheckout(true)
        timestamps()
        disableConcurrentBuilds()
        timeout(time: 30, unit: 'MINUTES')
    }

    environment {
        IMAGE_NAME = 'renanthera/kosanhub'
        APP_PORT = '8082'
        COMPOSE_PROJECT_NAME = 'kosanhub'
    }

    stages {

        stage('Checkout') {
            steps {
                echo 'Checking out KosanHub source code...'

                checkout scm

                script {
                    env.SHORT_SHA = bat(
                        returnStdout: true,
                        script: '@git rev-parse --short=7 HEAD'
                    ).trim()

                    env.CHECKED_OUT_BRANCH = bat(
                        returnStdout: true,
                        script: '@git branch --show-current'
                    ).trim()

                    echo "Branch: ${env.CHECKED_OUT_BRANCH}"
                    echo "Commit SHA: ${env.SHORT_SHA}"
                    echo "Build Number: ${env.BUILD_NUMBER}"
                }
            }
        }

        stage('Test') {
            steps {
                echo 'Running automated tests...'

                bat 'docker build --progress=plain --target test --tag %IMAGE_NAME%:test-%BUILD_NUMBER% .'

                echo 'Automated tests passed.'
            }
        }

        stage('Build Image') {
            steps {
                echo 'Building KosanHub production image...'

                bat 'docker build --progress=plain --target production --tag %IMAGE_NAME%:%BUILD_NUMBER% --tag %IMAGE_NAME%:%SHORT_SHA% --tag %IMAGE_NAME%:latest .'

                echo 'Production image built successfully.'
            }
        }

        stage('Push Image') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: 'kosanhub-dockerhub-credentials',
                        usernameVariable: 'DOCKERHUB_USERNAME',
                        passwordVariable: 'DOCKERHUB_TOKEN'
                    )
                ]) {
                    bat '''
                        @echo off

                        echo Logging in to Docker Hub...

                        powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "$env:DOCKERHUB_TOKEN | docker login --username $env:DOCKERHUB_USERNAME --password-stdin; exit $LASTEXITCODE"

                        if errorlevel 1 exit /b 1

                        echo Pushing build number image...

                        docker push %IMAGE_NAME%:%BUILD_NUMBER%
                        if errorlevel 1 exit /b 1

                        echo Pushing commit SHA image...

                        docker push %IMAGE_NAME%:%SHORT_SHA%
                        if errorlevel 1 exit /b 1

                        echo Pushing latest image...

                        docker push %IMAGE_NAME%:latest
                        if errorlevel 1 exit /b 1

                        echo All images pushed successfully.
                    '''
                }
            }
        }

        stage('Deploy') {
            steps {
                script {
                    def imageTag = "${env.IMAGE_NAME}:${env.BUILD_NUMBER}"

                    withEnv(["DOCKER_IMAGE=${imageTag}"]) {

                        withCredentials([
                            usernamePassword(
                                credentialsId: 'kosanhub-dockerhub-credentials',
                                usernameVariable: 'DOCKERHUB_USERNAME',
                                passwordVariable: 'DOCKERHUB_TOKEN'
                            )
                        ]) {
                            bat '''
                                @echo off

                                echo Validating Docker Compose configuration...

                                docker compose config --quiet
                                if errorlevel 1 exit /b 1

                                echo Logging in to Docker Hub...

                                powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "$env:DOCKERHUB_TOKEN | docker login --username $env:DOCKERHUB_USERNAME --password-stdin; exit $LASTEXITCODE"

                                if errorlevel 1 exit /b 1

                                echo Pulling images from Docker Hub...

                                docker compose pull
                                if errorlevel 1 exit /b 1

                                echo Starting KosanHub services...

                                docker compose up -d --no-build --remove-orphans app worker db redis
                                if errorlevel 1 exit /b 1

                                echo Checking application readiness...

                                powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "$deadline = (Get-Date).AddSeconds(90); do { try { $response = Invoke-WebRequest -UseBasicParsing -Uri ('http://localhost:' + $env:APP_PORT + '/health') -TimeoutSec 5; if ($response.StatusCode -eq 200) { exit 0 } } catch {}; Start-Sleep -Seconds 3 } while ((Get-Date) -lt $deadline); exit 1"

                                if errorlevel 1 exit /b 1

                                echo Application health check passed.

                                echo Checking container status...

                                docker compose ps
                                if errorlevel 1 exit /b 1

                                echo Deployment completed successfully.
                            '''
                        }
                    }
                }
            }
        }

        stage('Smoke Test') {
            steps {
                echo 'Running application health and database smoke tests...'

                bat 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File scripts\\smoke-test.ps1'

                echo 'Smoke Test passed successfully.'
            }
        }
    }

    post {

        success {
            echo '''
            ==========================================
            KOSANHUB CI/CD PIPELINE SUCCESS
            ==========================================
            All six stages completed successfully.
            Docker image deployed from Docker Hub.
            Application health check passed.
            Database connectivity verified.
            ==========================================
            '''
        }

        failure {
            echo '''
            ==========================================
            KOSANHUB CI/CD PIPELINE FAILED
            ==========================================
            One or more stages failed.
            Check the failed stage and console output.
            Deployment is not considered successful.
            ==========================================
            '''
        }

        always {
            script {
                echo "Build Number: ${env.BUILD_NUMBER}"
                echo "Image: ${env.IMAGE_NAME}:${env.BUILD_NUMBER}"
            }

            bat 'docker logout >NUL 2>&1 || exit /b 0'
        }
    }
}
