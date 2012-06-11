package com.github.rpository;

import java.io.IOException;
import java.io.OutputStream;

import org.apache.commons.compress.archivers.ArchiveOutputStream;
import org.apache.commons.compress.archivers.tar.TarArchiveOutputStream;
import org.apache.commons.compress.compressors.gzip.GzipCompressorOutputStream;

public class PackageOutputStream extends ArchiveOutputStream {

	private final OutputStream inner;
	private final TarArchiveOutputStream tar;

	public PackageOutputStream(OutputStream inner) throws IOException {
		this.inner = inner;
		tar = new TarArchiveOutputStream(new GzipCompressorOutputStream(inner));
	}

	@Override
	public void write(int arg0) throws IOException {

	}

}
