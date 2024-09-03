#include <Arduino.h>
#include <ETH.h>
#include <HTTPClient.h>
#include <Preferences.h>
#include <ArduinoJson.h>

// Configuration
#include "credentials.h"

// WT32-ETH01 Ethernet pins
#define ETH_TYPE ETH_PHY_LAN8720
#define ETH_ADDR 1
#define ETH_MDC_PIN 23
#define ETH_MDIO_PIN 18
#define ETH_POWER_PIN 16
#define ETH_CLK_MODE ETH_CLOCK_GPIO0_IN

const int ID_LENGTH = 16;
char deviceID[ID_LENGTH + 1] = {0};
bool isDeviceIDSet = false;
unsigned long lastCheckTime = 0;
const unsigned long CHECK_INTERVAL = 60000; // Check every 1 minute

Preferences preferences;

void generateRandomString(char* str, int length) {
  const char charset[] = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
  for (int i = 0; i < length; i++) {
    int index = random(0, sizeof(charset) - 1);
    str[i] = charset[index];
  }
  str[length] = '\0';
}

bool checkID() {
  if (!ETH.linkUp()) {
    Serial.println("Ethernet link is down. Retrying.");
    return false;
  }

  HTTPClient http;
  String url = String(API_ENDPOINT) + "/?action=check_id&id=" + String(deviceID);
  
  Serial.print("Checking ID: ");
  Serial.println(url);
  
  http.begin(url);
  int httpResponseCode = http.GET();
  String response = http.getString();
  http.end();
  
  Serial.print("HTTP Response code: ");
  Serial.println(httpResponseCode);
  Serial.print("Response: ");
  Serial.println(response);
  
  switch(httpResponseCode) {
    case 200:
      if (response == "exists") {
        Serial.println("ID exists and is valid.");
        return true;
      } else {
        Serial.println("Unexpected response from server. Treating as invalid.");
        return false;
      }
    case 400:
      Serial.println("Invalid ID format.");
      return false;
    case 404:
      Serial.println("ID not found on server.");
      clearEthConfig();
      return false;
    default:
      Serial.println("Server error or network issue.");
      return false;
  }
}

void clearEthConfig() {
  Serial.println("Clearing Ethernet configuration settings...");
  preferences.remove("useStaticIP");
  preferences.remove("static_ip");
  preferences.remove("gateway");
  preferences.remove("subnet");
  preferences.remove("dns1");
  preferences.remove("dns2");
  
  // Reset to DHCP
  ETH.config(INADDR_NONE, INADDR_NONE, INADDR_NONE);
  Serial.println("Ethernet configuration cleared and reset to DHCP.");

  // Wait for a valid IP
  Serial.println("Waiting for new IP address...");
  unsigned long startTime = millis();
  while (ETH.localIP() == INADDR_NONE) {
    if (millis() - startTime > 30000) {  // 30 seconds timeout
      Serial.println("Failed to obtain IP address. Restarting...");
      ESP.restart();
    }
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nNew IP address obtained: " + ETH.localIP().toString());
}

bool registerID() {
  if (!ETH.linkUp()) {
    Serial.println("Ethernet link is down. Retrying.");
    return false;
  }

  HTTPClient http;
  String url = String(API_ENDPOINT) + "/?action=get_id&id=" + String(deviceID);
  
  Serial.print("Registering ID: ");
  Serial.println(url);
  
  http.begin(url);
  int httpResponseCode = http.GET();
  String response = http.getString();
  http.end();
  
  Serial.print("HTTP Response code: ");
  Serial.println(httpResponseCode);
  Serial.print("Response: ");
  Serial.println(response);
  
  if (httpResponseCode == 200) {
    if (response == "added") {
      Serial.println("ID successfully registered.");
      return true;
    } else if (response == "exists") {
      Serial.println("ID already exists. Need to generate a new one.");
      return false;
    }
  }
  
  Serial.println("Failed to register ID. Will try again.");
  return false;
}

void saveIDToFlash() {
  preferences.putString("deviceID", String(deviceID));
  preferences.putBool("isDeviceIDSet", isDeviceIDSet);
  Serial.println("Saved ID to flash: " + String(deviceID));
  preferences.end();
  preferences.begin("device", false);
}

bool loadIDFromFlash() {
  String savedID = preferences.getString("deviceID", "");
  if (savedID.length() > 0) {
    savedID.toCharArray(deviceID, ID_LENGTH + 1);
    isDeviceIDSet = preferences.getBool("isDeviceIDSet", false);
    Serial.println("Loaded ID from flash: " + String(deviceID));
    return true;
  }
  Serial.println("No saved ID found in flash.");
  return false;
}

void setupETH() {
  pinMode(ETH_POWER_PIN, OUTPUT);
  digitalWrite(ETH_POWER_PIN, HIGH);
  delay(100);

  configureIP();

  ETH.begin(ETH_TYPE, ETH_ADDR, ETH_MDC_PIN, ETH_MDIO_PIN, ETH_POWER_PIN, ETH_CLK_MODE);
  
  Serial.println("Waiting for Ethernet connection...");
}

void configureIP() {
  bool useStaticIP = preferences.getBool("useStaticIP", false);
  
  if (useStaticIP) {
    String static_ip = preferences.getString("static_ip", "");
    String gateway = preferences.getString("gateway", "");
    String subnet = preferences.getString("subnet", "");
    String dns1 = preferences.getString("dns1", "");
    String dns2 = preferences.getString("dns2", "");

    if (static_ip.length() > 0 && gateway.length() > 0 && subnet.length() > 0) {
      IPAddress staticIP, gatewayIP, subnetMask, dns1IP, dns2IP;
      
      if (staticIP.fromString(static_ip) && gatewayIP.fromString(gateway) && 
          subnetMask.fromString(subnet) && dns1IP.fromString(dns1) && dns2IP.fromString(dns2)) {
        
        Serial.println("Configuring static IP...");
        if (ETH.config(staticIP, gatewayIP, subnetMask, dns1IP, dns2IP)) {
          Serial.println("Static IP configuration successful");
          return;
        } else {
          Serial.println("Static IP configuration failed");
        }
      } else {
        Serial.println("Invalid IP configuration in preferences");
      }
    } else {
      Serial.println("Incomplete static IP configuration in preferences");
    }
  }

  // If we reach here, either useStaticIP is false or static IP configuration failed
  Serial.println("Using DHCP for IP configuration");
  ETH.config(INADDR_NONE, INADDR_NONE, INADDR_NONE); // Reset to DHCP
}

void generateAndAssignNewID() {
  int attempts = 0;
  const int MAX_ATTEMPTS = 5;
  
  while (attempts < MAX_ATTEMPTS) {
    generateRandomString(deviceID, ID_LENGTH);
    
    Serial.print("Attempting to register new ID: ");
    Serial.println(deviceID);
    
    if (registerID()) {
      isDeviceIDSet = true;
      saveIDToFlash();
      Serial.print("New ID successfully registered and saved: ");
      Serial.println(deviceID);
      return;
    } else {
      attempts++;
      Serial.print("ID registration failed. Attempt ");
      Serial.print(attempts);
      Serial.print(" of ");
      Serial.println(MAX_ATTEMPTS);
      delay(1000 * attempts);  // Increasing delay between attempts
    }
  }
  
  Serial.println("Failed to register a new ID after maximum attempts. Will try again later.");
}

bool checkAndApplyConfig() {
  if (strlen(deviceID) == 0) {
    Serial.println("Device ID not set. Skipping config check.");
    return false;
  }

  HTTPClient http;
  String currentIP = ETH.localIP().toString();
  String url = String(API_ENDPOINT) + "/?action=check_config&id=" + String(deviceID) + "&ip=" + currentIP;
  http.begin(url);
  int httpResponseCode = http.GET();
  
  if (httpResponseCode == 200) {
    String response = http.getString();
    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, response);

    if (error) {
      Serial.print("deserializeJson() failed: ");
      Serial.println(error.c_str());
      return false;
    }

    bool configChanged = false;
    bool useStaticIP = doc["use_static_ip"];
    
    // Debug print
    Serial.println("Current preferences:");
    Serial.println("useStaticIP: " + String(preferences.getBool("useStaticIP", false)));
    Serial.println("static_ip: " + preferences.getString("static_ip", ""));
    Serial.println("gateway: " + preferences.getString("gateway", ""));

    Serial.println("\nReceived from API:");
    Serial.println("useStaticIP: " + String(useStaticIP));
    
    if (useStaticIP != preferences.getBool("useStaticIP", false)) {
      configChanged = true;
      Serial.println("useStaticIP changed");
    }

    preferences.putBool("useStaticIP", useStaticIP);

    if (useStaticIP) {
      IPAddress static_ip(doc["static_ip"][0], doc["static_ip"][1], doc["static_ip"][2], doc["static_ip"][3]);
      IPAddress gateway(doc["gateway"][0], doc["gateway"][1], doc["gateway"][2], doc["gateway"][3]);
      IPAddress subnet(doc["subnet"][0], doc["subnet"][1], doc["subnet"][2], doc["subnet"][3]);
      IPAddress dns1(doc["dns1"][0], doc["dns1"][1], doc["dns1"][2], doc["dns1"][3]);
      IPAddress dns2(doc["dns2"][0], doc["dns2"][1], doc["dns2"][2], doc["dns2"][3]);

      Serial.println("static_ip: " + static_ip.toString());
      Serial.println("gateway: " + gateway.toString());

      if (static_ip.toString() != preferences.getString("static_ip", "")) {
        configChanged = true;
        Serial.println("static_ip changed");
      }
      if (gateway.toString() != preferences.getString("gateway", "")) {
        configChanged = true;
        Serial.println("gateway changed");
      }
      if (subnet.toString() != preferences.getString("subnet", "")) {
        configChanged = true;
        Serial.println("subnet changed");
      }
      if (dns1.toString() != preferences.getString("dns1", "")) {
        configChanged = true;
        Serial.println("dns1 changed");
      }
      if (dns2.toString() != preferences.getString("dns2", "")) {
        configChanged = true;
        Serial.println("dns2 changed");
      }

      preferences.putString("static_ip", static_ip.toString());
      preferences.putString("gateway", gateway.toString());
      preferences.putString("subnet", subnet.toString());
      preferences.putString("dns1", dns1.toString());
      preferences.putString("dns2", dns2.toString());

      if (configChanged) {
        configureIP();  // Apply the new configuration
        Serial.println("Applied new static IP configuration");
      }
    } else {
      preferences.putBool("useStaticIP", false);
      if (configChanged) {
        configureIP();  // Reset to DHCP
        Serial.println("Switched to DHCP configuration");
      }
    }

    Serial.println("Config changed: " + String(configChanged));

    http.end();
    return configChanged;
  } else {
    Serial.println("Error checking config. HTTP Response code: " + String(httpResponseCode));
    http.end();
    return false;
  }
}

void setup() {
  Serial.begin(115200);
  preferences.begin("device", false);
  setupETH();
  
  // Wait for Ethernet connection and valid IP
  unsigned long startTime = millis();
  while (ETH.localIP() == INADDR_NONE) {
    if (millis() - startTime > 30000) {  // 30 seconds timeout
      Serial.println("Failed to obtain IP address. Restarting...");
      ESP.restart();
    }
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nEthernet connected");
  Serial.print("IP address: ");
  Serial.println(ETH.localIP());
  
  if (loadIDFromFlash() && isDeviceIDSet) {
    Serial.println("Loaded existing ID: " + String(deviceID));
    if (checkID()) {
      Serial.println("Loaded ID is valid.");
    } else {
      Serial.println("Loaded ID is not valid. Generating new ID...");
      generateAndAssignNewID();
    }
  } else {
    generateAndAssignNewID();
  }
  
  checkAndApplyConfig();
}

void loop() {
  unsigned long currentTime = millis();
  
  if (currentTime - lastCheckTime >= CHECK_INTERVAL) {
    lastCheckTime = currentTime;
    
    Serial.println("Performing periodic ID and config check...");
    if (isDeviceIDSet && checkID()) {
      Serial.println("ID is still valid.");
      if (checkAndApplyConfig()) {
        Serial.println("Configuration updated.");
      } else {
        Serial.println("No configuration changes.");
      }
    } else {
      Serial.println("ID not valid or network issue. Generating new ID...");
      generateAndAssignNewID();
      checkAndApplyConfig(); // Check config after getting new ID
    }
  }
  
  delay(1000);
}