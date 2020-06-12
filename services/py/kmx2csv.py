# -*- coding: utf-8 -*-
from bs4 import BeautifulSoup
from zipfile import ZipFile
import traceback
import unicodecsv as csv
import os
import sys
import re
import io

# python3 kmx2csv.py test.kmz
# dependencies: /usr/bin/python -m pip install -U bs4
# python -m pip install BeautifulSoup4
# python kmx2csv.py . kmz proyectos ./tmp


def main():
    if len(sys.argv) < 3 or len(sys.argv) > 4:
        print('Usage: ' + sys.argv[0] +
              ' file_extesion inputfile [outputpath]')
        os._exit(1)

    if len(sys.argv) == 3:
        sys.argv.append('.')

    print(
        'Args:' + sys.argv[0] + ' - ' + sys.argv[1] + ' - ' + sys.argv[2] + ' - ' + sys.argv[3])

    try:
        file_extesion = sys.argv[1]
        in_file = sys.argv[2]
        out_path = sys.argv[3]
        out_file = in_file + '_out.csv'

        if file_extesion == 'kml':
            process_kml(in_file, out_file)
        elif file_extesion == 'kmz':
            process_kmz(in_file, out_file, out_path)

    except:
        print('Error: ', sys.exc_info())
        traceback.print_exc()
        os._exit(1)


def process_kmz(in_file, out_file, out_path):
    ''' Extrae todos los KML de un KMZ y los procesa a todos en un directorio temporal '''
    # Descomprimo el KMZ
    kmz = ZipFile(in_file, 'r')
    kmz.extractall(out_path)

    for _, _, files in os.walk(out_path):
        for i, a_file in enumerate(files):
            _, extension = os.path.splitext(a_file)
            if extension == '.kml':
                process_kml(a_file, out_file + str(i).zfill(3))


def process_kml(in_file, out_file):
    ''' Procesa un archivo KML '''
    with io.open(in_file, 'r', encoding="utf8") as kml_file:
        kml_file = kml_file.read().replace(
            u"’", "'").replace(u"“", "\"").replace(u"”", "\"")
        s = BeautifulSoup(kml_file, 'xml')
        with open(out_file, 'wb') as csvfile:
            writer = csv.writer(csvfile,
                                delimiter=',',
                                quotechar='"',
                                doublequote=True,
                                skipinitialspace=False,
                                lineterminator='\n',
                                quoting=csv.QUOTE_ALL)
            writer.writerow(createTitle())
            doc = Document(s)
            for folder in doc.get_folders():
                for placemark in folder.get_placemarks():
                    for place in placemark.get_places():
                        writer.writerow(createRow(folder, placemark, place))
    print(out_file)


def createTitle():
    title = [
        "Nombre Folder",
        "Descripcion Folder",
        "Nombre Placemark",
        "Descripcion Placemark",
        "ExtendedData",
        "Nombre Place",
        "Descripcion Place",
        "Longitude",
        "Latitude",
        "Altitude",
        "GeoJson"
    ]
    return title


def createRow(folder, placemark, place):
    row = place.get_row()  # 4 elementos: x,y,z,GeoJson
    row.insert(0, formatLine(folder.get_name()))
    row.insert(1, formatLine(folder.get_description()))
    row.insert(2, formatLine(placemark.get_name()))
    row.insert(3, formatLine(placemark.get_description()))
    row.insert(4, formatLine(placemark.get_extended_data()))
    row.insert(5, formatLine(place.get_name()))
    row.insert(6, formatLine(place.get_description()))
    #row = [placemark.get_extended_data()]
    return row


def formatLine(line):
    # return line
    if line == None:
        return "\n"
    return line + "\n"


class Document:
    def __init__(self, xml):
        self.name = ""
        self.description = ""
        self.folders = []
        self.__parse__(xml)

    def __parse__(self, xml):
        for folder in xml.find_all('Folder'):
            self.folders.append(Folder(folder))

    def get_folders(self):
        return self.folders


class Folder:
    def __init__(self, xml):
        self.name = ""
        self.description = ""
        self.placemarks = []
        self.__parse__(xml)

    def __parse__(self, xml):
        self.description = xml.find('description')
        self.name = xml.find('name')
        for placemark in xml.find_all('Placemark'):
            self.placemarks.append(Placemark(placemark))

    def get_name(self):
        return self.name.string if self.name else ""

    def get_description(self):
        return self.description.string if self.description else ""

    def get_placemarks(self):
        return self.placemarks


class Placemark:
    def __init__(self, xml):
        self.name = ""
        self.description = ""
        self.places = []
        self.extended_data = ""
        self.__parse__(xml)

    def __parse__(self, xml):
        self.name = xml.find('name')
        self.description = xml.find('description')
        for point in xml.find_all('Point'):
            self.places.append(Point(point))
        for polygon in xml.find_all('Polygon'):
            self.places.append(Polygon(polygon))
        for address in xml.find_all('address'):
            self.places.append(Address(address))
        ext_data = xml.find('ExtendedData')
        self.extended_data = ExtendedData(
            ext_data).get_data() if ext_data else ""

    def get_places(self):
        return self.places

    def get_name(self):
        return self.name.string if self.name else ""

    def get_description(self):
        return self.description.string if self.description else ""

    def get_extended_data(self):
        return self.extended_data


class Point:
    def __init__(self, xml):
        self.name = ""
        self.description = ""
        self.coordinates = []
        self.__parse__(xml)

    def __parse__(self, xml):
        for coordinate in xml.find_all('coordinates'):
            for coord_str in coordinate:
                self.coordinates.append(Coordinate(coord_str))

    def get_row(self):
        row = self.coordinates[0].get_xyz_row()
        row.append(self.__get_geodata())
        return row

    def get_name(self):
        return self.name.string if self.name else ""

    def get_description(self):
        return self.description.string if self.description else ""

    def __get_geodata(self):
        coords = []
        for coord in self.coordinates:
            coords.append(coord.get_xyz_row())
        return {
            "type": "Point",
            "coordinates": [coords]
        }


class Address:
    def __init__(self, xml):
        self.name = ""
        self.description = ""
        self.coordinates = []
        self.__parse__(xml)

    def __parse__(self, xml):
        xy = xml.text.strip().split(" ")
        coord_str = xy[1] + "," + xy[0] + ",0"
        self.coordinates.append(Coordinate(coord_str))

    def get_row(self):
        row = self.coordinates[0].get_xyz_row()
        row.append(self.__get_geodata())
        return row

    def __get_geodata(self):
        coords = []
        for coord in self.coordinates:
            coords.append(coord.get_xyz_row())
        return {
            "type": "Point",
            "coordinates": [coords]
        }

    def get_name(self):
        return self.name.string if self.name else ""

    def get_description(self):
        return self.description.string if self.description else ""


class Polygon:
    def __init__(self, xml):
        self.name = ""
        self.description = ""
        self.coordinates = []
        self.__parse__(xml)

    def __parse__(self, xml):
        for coordinate in xml.find_all('coordinates'):
            for coord_strs in coordinate:
                coord_str = coord_strs.split(' ')
                for string_with_coordinate in coord_str:
                    string_with_coordinate = string_with_coordinate.strip('\n')
                    if string_with_coordinate != "":
                        self.coordinates.append(
                            Coordinate(string_with_coordinate))

    def get_row(self):
        row = ["", "", ""]
        row.append(self.__get_geodata())
        return row

    def get_coordinates(self):
        return self.coordinates

    def __get_geodata(self):
        coords = []
        for coord in self.coordinates:
            coords.append(coord.get_xy_row_float())
        m = {
            "type": "Polygon",
            "coordinates": [coords]
        }
        return str(m).replace("'", "\"")

    def get_name(self):
        return self.name.string if self.name else ""

    def get_description(self):
        return self.description.string if self.description else ""


class Coordinate:
    def __init__(self, coord_str):
        try:
            xyz = coord_str.strip().split(",")
            self.x = xyz[0].replace(',', '.')
            self.y = xyz[1].replace(',', '.')
            self.z = xyz[2].replace(',', '.')
        except:
            xyz = coord_str.strip().split(",")
            self.x = xyz[0].replace(',', '.')
            self.y = xyz[1].replace(',', '.')
            self.z = 0

    def get_xyz_row(self):
        return [self.x, self.y, self.z]

    def get_xy_row(self):
        return [self.x, self.y]

    def get_xy_row_float(self):
        return [float(self.x), float(self.y)]


class ExtendedData:
    def __init__(self, xml):
        self.data = {}
        self.__parse__(xml)

    def __parse__(self, xml):
        for adata in xml.find_all('SchemaData'):
            for data in adata.find_all('SimpleData'):
                if (data["name"] != None and data.string != None):
                    self.data[data["name"]] = data.string
        for data in xml.find_all('Data'):
            data_value = data.find('value').contents
            if len(data_value) != 0:
                data_value = data_value[0]
                data_value = data_value.replace('\n', '')
                data_value = data_value.replace('\xa0', '')
                self.data[data["name"]] = data_value

    def get_data(self):
        extData = ""
        for key in self.data:
            extData = extData + key + ":" + self.data[key] + "\n"
        return extData

    def get_row(self):
        extData = ""
        for key in self.data:
            extData = extData + key + ":" + self.data[key] + "\n"
        return extData


if __name__ == '__main__':
    main()